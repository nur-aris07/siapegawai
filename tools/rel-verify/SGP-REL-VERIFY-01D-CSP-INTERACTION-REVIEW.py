#!/usr/bin/env python3
"""REL-VERIFY-01D / C-05. Manual-interaction CSP monitor for disposable staging.

Observes only counts of CSP events, AJAX responses and JS exceptions while the
operator interacts in a visible browser. No passwords, URLs, bodies, HAR,
screenshots, cookies or session state are collected or saved.
"""
from __future__ import annotations
import argparse
import datetime as dt
import hashlib
import ipaddress
import json
from pathlib import Path
import re
import sys
from urllib.parse import urlparse

ROOT = Path(__file__).resolve().parents[2]
EVIDENCE_ROOT = ROOT / '.siapegawai-backup' / 'rel-verify-01d'
ROLE_START = {'member': '/quiz-kilat', 'admin': '/admin/peserta'}
CSP_LISTENER = r'''(() => {
  document.addEventListener('securitypolicyviolation', (event) => {
    const value = String(event.effectiveDirective || event.violatedDirective || 'unknown').slice(0,40);
    if (/^[A-Za-z0-9-]{1,40}$/.test(value) && typeof window.__sgpReportCspEvent === 'function') {
      try { Promise.resolve(window.__sgpReportCspEvent(value)).catch(() => {}); } catch (_) {}
    }
  });
})();'''


def allowed_origin(value: str, ack: bool) -> str:
    p = urlparse(value)
    try:
        host, port = p.hostname, p.port
    except ValueError as exc:
        raise ValueError('Invalid host or port') from exc
    if (not host or p.scheme not in ('http','https') or p.username or p.password or p.query or
        p.fragment or p.params or p.path not in ('','/')):
        raise ValueError('Origin must be scheme://host[:port], without credentials or paths')
    if host.lower() in ('localhost','127.0.0.1','::1'):
        return value.rstrip('/')
    try:
        ip = ipaddress.ip_address(host)
    except ValueError:
        ip = None
    if not ack or p.scheme != 'https':
        raise ValueError('Remote target must be an acknowledged HTTPS staging endpoint')
    if ip is not None and ip.is_private:
        return value.rstrip('/')
    if ip is None and re.search(r'(^|[.-])(staging|stage|qa)([.-]|$)',host.lower()):
        return value.rstrip('/')
    raise ValueError('Only private HTTPS or named staging/QA origin allowed')


def safe_evidence_directory(value: str) -> Path:
    if not value or '\x00' in value:
        raise ValueError('Invalid evidence location')
    p = Path(value)
    if '..' in p.parts:
        raise ValueError('Parent path traversal forbidden')
    p = p if p.is_absolute() else ROOT / p
    base = EVIDENCE_ROOT.resolve()
    resolved = p.resolve()
    if resolved == base or base not in resolved.parents:
        raise ValueError('Evidence must be inside ignored rel-verify-01d directory')
    current = p
    while current != ROOT and current != current.parent:
        if current.is_symlink():
            raise ValueError('Symlink evidence directory forbidden')
        current = current.parent
    return resolved


def parse_args(argv=None):
    p = argparse.ArgumentParser(description='Manual CSP interaction review: default dry-run; no DB operations')
    p.add_argument('--run', action='store_true')
    p.add_argument('--ack-staging', action='store_true')
    p.add_argument('--roles', default='member,admin', help='member,admin or either alone')
    p.add_argument('--base-url', default='http://127.0.0.1:8000')
    p.add_argument('--out-dir', default='.siapegawai-backup/rel-verify-01d/csp-interactions')
    return p.parse_args(argv)


def parse_roles(value: str) -> list[str]:
    roles = value.split(',')
    if len(roles) != len(set(roles)) or not roles or any(role not in ROLE_START for role in roles):
        raise ValueError('Allowed roles: member,admin (no duplicates)')
    return roles


def self_test() -> None:
    for bad in ('https://example.com', 'http://example.com', 'http://127.0.0.1:8000/?token=x',
                'http://admin:pass@127.0.0.1:8000', 'https://production.example.org'):
        try:
            allowed_origin(bad, True)
        except ValueError:
            continue
        raise RuntimeError('Unsafe origin guard failed')
    assert allowed_origin('http://127.0.0.1:8000', True) == 'http://127.0.0.1:8000'
    assert parse_roles('member,admin') == ['member','admin']
    for bad in ('member,member', 'root', 'member,root'):
        try:
            parse_roles(bad)
        except ValueError:
            continue
        raise RuntimeError('Unsafe role guard failed')
    # Interactive review cannot silently pass when operator presses Enter or types a typo.
    fake_out = lambda _message: None
    for answers, expected in ((['',' SELESAI '], True), (['','',''], False), (['BATAL'], False)):
        stream = iter(answers)
        assert confirm_review(lambda _prompt: next(stream), fake_out) is expected
    print('[SELF-TEST PASS] Origin, role, guard, and explicit-confirmation validation')



def confirm_review(input_fn=input, say=print) -> bool:
    """Require an explicit operator attestation; avoid opaque BLOCKED on blank Enter.

    This is a *manual* acknowledgement, NOT technical proof of a CSP-free UI.
    """
    for attempt in range(1, 4):
        value = input_fn('[MANUAL] Type exactly SELESAI to confirm browser interactions (or BATAL): ').strip().upper()
        if value == 'SELESAI':
            say('[MANUAL] Confirmation SELESAI recorded (owner-reported observation, not release sign-off).')
            return True
        if value == 'BATAL':
            say('[MANUAL] Browser interaction review cancelled by operator.')
            return False
        if attempt < 3:
            say(f'[ACTION] Confirmation not recognized ({attempt}/3). ENTER alone is not confirmation; type SELESAI.')
    say('[BLOCKED] Manual confirmation not provided after 3 attempts.')
    return False


def observe(origin: str, role: str) -> dict:
    from playwright.sync_api import sync_playwright
    counters = {'ajax':0, 'ajax_failures':0, 'js_exceptions':0,
                'document_missing_csp':0, 'document_enforce':0,
                'csp_events':{}, 'document_200':0}
    def on_csp(_source, directive):
        if isinstance(directive, str) and re.fullmatch(r'[A-Za-z0-9-]{1,40}',directive):
            counters['csp_events'][directive] = min(1000000, counters['csp_events'].get(directive,0)+1)

    with sync_playwright() as pw:
        browser = pw.chromium.launch(headless=False)
        context = browser.new_context(ignore_https_errors=False, service_workers='block')
        context.expose_binding('__sgpReportCspEvent', on_csp)
        context.add_init_script(CSP_LISTENER)
        page = context.new_page()
        def on_response(res):
            try:
                typ = res.request.resource_type
                if typ in ('xhr','fetch'):
                    counters['ajax'] += 1
                    if res.status >= 400:
                        counters['ajax_failures'] += 1
                elif typ == 'document' and res.url.startswith(origin+'/'):
                    if res.status == 200:
                        counters['document_200'] += 1
                    headers = res.headers
                    if not headers.get('content-security-policy-report-only','').strip():
                        counters['document_missing_csp'] += 1
                    if headers.get('content-security-policy','').strip():
                        counters['document_enforce'] += 1
            except Exception:
                counters['js_exceptions'] += 1
        page.on('response', on_response)
        page.on('pageerror', lambda _exc: counters.__setitem__('js_exceptions', counters['js_exceptions']+1))
        try:
            page.goto(origin+'/login', wait_until='domcontentloaded', timeout=20000)
            print(f'[MANUAL] {role}: login with a disposable test account in visible Chromium.')
            print('[MANUAL] Do not paste credentials, cookies, or HAR in terminal/chat.')
            landing_ok = False
            for attempt in range(1, 4):
                input('[MANUAL] Verify login completed in Chromium, then press ENTER to check role landing: ')
                response = page.goto(origin+ROLE_START[role], wait_until='domcontentloaded', timeout=20000)
                current = urlparse(page.url)
                landing_ok = bool(response and response.status == 200 and
                                  (current.scheme,current.hostname,current.port) ==
                                  (urlparse(origin).scheme,urlparse(origin).hostname,urlparse(origin).port) and
                                  current.path == ROLE_START[role])
                if landing_ok:
                    break
                if attempt < 3:
                    print('[ACTION] Role landing not confirmed. Check the dummy account role/login in Chromium; retry (no secrets in terminal).')
            if not landing_ok:
                print('[BLOCKED] Role landing still not confirmed after 3 checks; no CSP acceptance inferred.')
                return {'role':role,'status':'BLOCKED_ROLE_LANDING','landing_ok':False,'counters':counters,
                        'manual_feedback_5s':'NOT_REPORTED'}
            # Do not count AJAX initiated during login or initial shell load as interaction evidence.
            counters['ajax'] = 0
            counters['ajax_failures'] = 0
            print(f'[MANUAL] {role} landing OK. Interact IN THIS TAB (AJAX, forms/navigation and PDF as applicable).')
            if role == 'member':
                print('[MANUAL] If a disposable quiz/unused quota exists, answer a question; verify lock, feedback >=5s, and next.')
                print('[MANUAL] Also check authorized/denied PDF using test-only enrollment if available.')
            else:
                print('[MANUAL] Search/paginate DataTables and exercise safe readonly filters; avoid modifying real users.')
            print('[MANUAL] Review actual AJAX/search/navigation behavior before confirming; ENTER alone does not count.')
            manual_confirmed = confirm_review()
            final = urlparse(page.url)
            base = urlparse(origin)
            still_same_origin = (final.scheme, final.hostname, final.port) == (base.scheme,base.hostname,base.port)
            feedback = 'NOT_APPLICABLE'
            if role == 'member':
                typed = input('[MANUAL] 5-second feedback observed? Type PASS, FAIL or BLOCKED: ').strip().upper()
                feedback = typed if typed in ('PASS','FAIL','BLOCKED') else 'NOT_REPORTED'
            violations = sum(counters['csp_events'].values())
            if not manual_confirmed or not still_same_origin:
                status = 'BLOCKED_MANUAL_REVIEW_NOT_CONFIRMED'
            elif counters['document_missing_csp'] or counters['document_enforce']:
                status = 'BLOCKED_MISSING_OR_ENFORCED_CSP'
            elif violations or counters['ajax_failures'] or counters['js_exceptions']:
                status = 'FLAG_REQUIRES_REVIEW'
            elif counters['ajax'] == 0:
                status = 'BLOCKED_NO_AJAX_OBSERVED'
            elif role == 'member' and feedback != 'PASS':
                status = 'PARTIAL_FEEDBACK_NOT_CONFIRMED'
            else:
                status = 'CANDIDATE_INTERACTION_ONLY'
            return {'role':role,'status':status,'landing_ok':True,'counters':counters,
                    'manual_feedback_5s':feedback}
        finally:
            context.close()
            browser.close()


def main(argv=None) -> int:
    args = parse_args(argv)
    try:
        origin=allowed_origin(args.base_url,args.ack_staging)
        roles=parse_roles(args.roles)
        out=safe_evidence_directory(args.out_dir)
    except ValueError as exc:
        print('[STOP] '+str(exc), file=sys.stderr)
        return 2
    if not args.run:
        self_test()
        print(f'[DRY RUN] Roles={",".join(roles)}; no browser, network, database or files changed.')
        return 0
    if not args.ack_staging:
        print('[STOP] --ack-staging required; use only disposable staging/test accounts.',file=sys.stderr)
        return 2
    results=[]
    try:
        for role in roles:
            result=observe(origin,role)
            results.append(result)
            print(f"[CHECK] {role}: {result['status']} AJAX={result['counters']['ajax']} "
                  f"CSP_EVENTS={sum(result['counters']['csp_events'].values())} "
                  f"JS_ERRORS={result['counters']['js_exceptions']}")
        stamp=dt.datetime.now(dt.timezone(dt.timedelta(hours=7))).strftime('%Y%m%dT%H%M%S%f%z')
        destination=out/stamp
        destination.mkdir(parents=True,exist_ok=False)
        payload={'work_id':'REL-VERIFY-01D','type':'manual_csp_interaction_observation',
                 'observed_wib':dt.datetime.now(dt.timezone(dt.timedelta(hours=7))).isoformat(),
                 'origin_sha256_prefix':hashlib.sha256(origin.encode()).hexdigest()[:12],
                 'results':results,
                 'limitations':['Owner-driven observations; no timing automation or screenshot evidence',
                                'Local production-mode is not independently isolated staging',
                                'Cannot establish database restore/media integrity or RTO/RPO',
                                'A-21/V-09 remain OPEN pending staging review and sign-off']}
        (destination/'REL-VERIFY-01D-CSP-INTERACTIONS.json').write_text(json.dumps(payload,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
        print('[EVIDENCE] Redacted summary stored only under ignored .siapegawai-backup/rel-verify-01d/.')
        print('[GATE] A-21/V-09 and REL-VERIFY-01D owner acceptance still OPEN.')
        return 0 if all(r['status']=='CANDIDATE_INTERACTION_ONLY' for r in results) else 1
    except (KeyboardInterrupt,EOFError):
        print('[STOP] Interactive review cancelled; no gate accepted.',file=sys.stderr)
        return 2
    except Exception:
        print('[STOP] Browser review failed; details redacted, no gate accepted.',file=sys.stderr)
        return 2

if __name__=='__main__':
    sys.exit(main())
