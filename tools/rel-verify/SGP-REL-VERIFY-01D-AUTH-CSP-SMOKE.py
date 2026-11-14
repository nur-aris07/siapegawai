#!/usr/bin/env python3
"""REL-VERIFY-01D: staging-only CSP report-only smoke for account/member/admin GET shells.

No password automation, cookie/session export, screenshots, HAR, URLs with query,
request/response bodies, console strings or raw CSP policies in evidence.
Authenticated login is completed manually in a visible, ephemeral Chromium window.
This tool is observational, not a release gate or enforcement decision.
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

SCRIPT_DIR = Path(__file__).resolve().parent
PROJECT_ROOT = SCRIPT_DIR.parent.parent
EVIDENCE_ROOT = PROJECT_ROOT / ".siapegawai-backup" / "rel-verify-01d"
ROLE_PATHS = {
    "account": ("/login", "/daftar", "/lupa-password"),
    "member": ("/dashboard", "/profil", "/kelas-saya", "/quiz-kilat", "/peserta/tryout"),
    "admin": ("/admin", "/admin/kelas", "/admin/quiz-kilat", "/admin/peserta"),
}
CSP_INIT_SCRIPT = """(() => {
  window.__sgpCspCounts = Object.create(null);
  document.addEventListener('securitypolicyviolation', (event) => {
    const key = String(event.effectiveDirective || event.violatedDirective || 'unknown').slice(0, 50);
    if (/^[a-zA-Z0-9-]+$/.test(key)) {
      window.__sgpCspCounts[key] = (window.__sgpCspCounts[key] || 0) + 1;
    }
  });
})();"""


def parse_args(argv: list[str] | None = None) -> argparse.Namespace:
    p = argparse.ArgumentParser(description="Staging-only, GET-only authenticated CSP shell smoke")
    p.add_argument("--run", action="store_true", help="explicitly visit the staging origin")
    p.add_argument("--base-url", default="http://127.0.0.1:8001", help="isolated local or HTTPS staging origin")
    p.add_argument("--ack-staging", action="store_true", help="affirm remote origin is disposable staging, not production")
    p.add_argument("--role", choices=tuple(ROLE_PATHS), required=True)
    p.add_argument("--out-dir", default=".siapegawai-backup/rel-verify-01d/csp")
    p.add_argument("--browser-path", help="optional Chromium executable path")
    return p.parse_args(argv)


def allowed_origin(value: str, ack_staging: bool) -> str:
    parsed = urlparse(value)
    try:
        host = parsed.hostname
        port = parsed.port
    except ValueError as exc:
        raise ValueError("Invalid origin port or hostname") from exc
    if (parsed.scheme not in ("http", "https") or not host or parsed.username or
            parsed.password or parsed.query or parsed.fragment or parsed.params or
            parsed.path not in ("", "/")):
        raise ValueError("Use scheme://host[:port] only; credentials, path and query forbidden")
    if host.lower() in ("localhost", "127.0.0.1", "::1"):
        return value.rstrip("/")
    try:
        ip = ipaddress.ip_address(host)
    except ValueError:
        ip = None
    # Private IPs require explicit acknowledgement and HTTPS, just like named remote QA.
    if ip is not None and ip.is_private and parsed.scheme == "https" and ack_staging:
        return value.rstrip("/")
    if (ip is not None or parsed.scheme != "https" or not ack_staging or
            not re.search(r"(^|[.-])(staging|stage|qa)([.-]|$)", host.lower())):
        raise ValueError("Remote must be an explicitly acknowledged HTTPS staging/QA hostname")
    return value.rstrip("/")


def evidence_directory(value: str) -> Path:
    raw = Path(value)
    if not value or "\x00" in value or ".." in raw.parts:
        raise ValueError("Unsafe evidence directory")
    path = raw if raw.is_absolute() else PROJECT_ROOT / raw
    resolved = path.resolve()
    expected = EVIDENCE_ROOT.resolve()
    if resolved == expected or expected not in resolved.parents:
        raise ValueError("Evidence must be under .siapegawai-backup/rel-verify-01d/")
    # Block symlink components, including symlinks already present in output parents.
    parent = path
    while parent != PROJECT_ROOT and parent != parent.parent:
        if parent.is_symlink():
            raise ValueError("Symlink in evidence path forbidden")
        parent = parent.parent
    return resolved


def directive_counts(raw: object) -> dict[str, int]:
    if not isinstance(raw, dict):
        return {}
    cleaned = {}
    for key, value in raw.items():
        if (isinstance(key, str) and re.fullmatch(r"[a-zA-Z0-9-]{1,50}", key)
                and isinstance(value, int) and not isinstance(value, bool)):
            cleaned[key] = min(max(value, 0), 1_000_000)
    return cleaned


def same_origin(actual: str, expected: str) -> bool:
    a, b = urlparse(actual), urlparse(expected)
    return (a.scheme, a.hostname, a.port) == (b.scheme, b.hostname, b.port)


def report_row(response: object, page: object, path: str, origin: str) -> dict:
    row = {
        "path": path,
        "http_status": None,
        "same_origin": False,
        "expected_path": False,
        "report_only_present": False,
        "directives": [],
        "enforce_present": False,
        "violations_by_directive": {},
        "navigation_error": False,
    }
    if response is None:
        return row
    row["http_status"] = response.status
    current = urlparse(page.url)
    row["same_origin"] = same_origin(page.url, origin)
    row["expected_path"] = current.path == path and not current.query and not current.fragment
    if not row["same_origin"] or not row["expected_path"]:
        return row
    headers = {str(k).lower(): str(v) for k, v in response.headers.items()}
    raw = headers.get("content-security-policy-report-only", "")
    row["report_only_present"] = bool(raw.strip())
    row["directives"] = sorted({
        part.split()[0].lower() for part in raw.split(";")
        if part.strip() and re.fullmatch(r"[A-Za-z-]+", part.split()[0])
    })
    row["enforce_present"] = bool(headers.get("content-security-policy", "").strip())
    page.wait_for_timeout(1000)
    row["violations_by_directive"] = directive_counts(
        page.evaluate("Object.assign({}, window.__sgpCspCounts || {})"))
    return row


def classify(rows: list[dict], login_confirmed: bool) -> str:
    if not login_confirmed:
        return "BLOCKED_AUTHENTICATION_NOT_CONFIRMED"
    if not rows or not all(r["report_only_present"] for r in rows):
        return "BLOCKED_MISSING_REPORT_ONLY"
    if any(r["navigation_error"] or r["http_status"] != 200 or not r["same_origin"]
           or not r["expected_path"] or r["enforce_present"]
           or sum(r["violations_by_directive"].values()) for r in rows):
        return "FLAG_REQUIRES_REVIEW"
    return "CANDIDATE_SHELL_ONLY"


def run_browser(origin: str, role: str, browser_path: str | None) -> tuple[bool, list[dict]]:
    from playwright.sync_api import sync_playwright

    rows: list[dict] = []
    login_confirmed = role == "account"
    with sync_playwright() as playwright:
        opts: dict = {"headless": role == "account"}
        if browser_path:
            executable = Path(browser_path)
            if not executable.is_file():
                raise RuntimeError("Chromium executable path is invalid")
            opts["executable_path"] = str(executable)
        browser = playwright.chromium.launch(**opts)
        context = browser.new_context(ignore_https_errors=False, service_workers="block")
        context.add_init_script(CSP_INIT_SCRIPT)
        try:
            if role != "account":
                login = context.new_page()
                try:
                    login.goto(origin + "/login", wait_until="domcontentloaded", timeout=20000)
                    if not same_origin(login.url, origin):
                        raise RuntimeError("Login redirected outside staging origin")
                    print("[MANUAL] Login in the visible Chromium window using a disposable staging account.")
                    print("[MANUAL] Do NOT type passwords or session tokens in this terminal.")
                    try:
                        input("[MANUAL] After successful login in browser, press ENTER here (or Ctrl+C to cancel): ")
                    except EOFError as exc:
                        raise RuntimeError("Interactive terminal required for manual login") from exc
                    expected_home = ROLE_PATHS[role][0]
                    probe = login.goto(origin + expected_home, wait_until="domcontentloaded", timeout=20000)
                    path = urlparse(login.url).path
                    login_confirmed = bool(probe and probe.status == 200 and
                                           same_origin(login.url, origin) and path == expected_home)
                    if not login_confirmed:
                        print("[BLOCKED] Staging role landing page was not reached; no role scan run.")
                finally:
                    login.close()
            if login_confirmed:
                for path in ROLE_PATHS[role]:
                    page = context.new_page()
                    row: dict = {"path": path, "navigation_error": True,
                                 "http_status": None, "same_origin": False,
                                 "expected_path": False, "report_only_present": False,
                                 "directives": [], "enforce_present": False,
                                 "violations_by_directive": {}}
                    try:
                        response = page.goto(origin + path, wait_until="domcontentloaded", timeout=20000)
                        row = report_row(response, page, path, origin)
                    except Exception:
                        row["navigation_error"] = True
                    finally:
                        page.close()
                    rows.append(row)
                    n = sum(row["violations_by_directive"].values())
                    print(f"[CHECK] {path}: HTTP={row['http_status']} report-only={row['report_only_present']} violations={n} redirected={not row['expected_path']}")
        finally:
            context.close()
            browser.close()
    return login_confirmed, rows


def write_evidence(outdir: Path, origin: str, role: str, status: str, confirmed: bool, rows: list[dict]) -> None:
    # Every execution gets a new immutable evidence folder; never overwrite older QA runs.
    stamp = dt.datetime.now(dt.timezone(dt.timedelta(hours=7))).strftime("%Y%m%dT%H%M%S%f%z")
    outdir = outdir / (role + "-" + stamp)
    outdir.mkdir(parents=True, exist_ok=False)
    obj = {
        "tool": "REL-VERIFY-01D",
        "observed_at_wib": dt.datetime.now(dt.timezone(dt.timedelta(hours=7))).isoformat(),
        "origin_sha256_prefix": hashlib.sha256(origin.encode("utf-8")).hexdigest()[:12],
        "role": role,
        "role_landing_reached": confirmed,
        "status": status,
        "scope": "Known GET shells only; manually authenticated disposable staging account. Not a security or release sign-off.",
        "routes": rows,
        "a21": "OPEN_PENDING_REVIEW",
        "v09": "OPEN_PENDING_REVIEW",
        "limitations": "Does not test AJAX/form actions, QQ feedback timing, PDF access, CSP proxy enforcement or restore. Verify separately.",
    }
    (outdir / f"REL-VERIFY-01D-CSP-{role}.json").write_text(
        json.dumps(obj, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    md = ["# REL-VERIFY-01D — " + role + " CSP Shell Smoke", "",
          "**Observation only. A-21 and V-09 are OPEN pending reviewer sign-off.**", "",
          f"- Status: `{status}`", f"- Role shell: `{role}`", "- Browser login: manual (if applicable)", "",
          "| GET path | HTTP | Same path | CSP report-only | CSP events | Enforce header |",
          "|---|---:|---|---|---:|---|"]
    for row in rows:
        md.append(f"| `{row['path']}` | {row['http_status'] or 'ERROR'} | "
                  f"{'yes' if row['expected_path'] else 'no'} | "
                  f"{'yes' if row['report_only_present'] else 'no'} | "
                  f"{sum(row['violations_by_directive'].values())} | "
                  f"{'yes' if row['enforce_present'] else 'no'} |")
    md += ["", "Not covered: AJAX/DataTables behavior, 5-second QQ feedback, private files, negative-role UAT, backup/restore, staging proxy audit.", ""]
    (outdir / f"REL-VERIFY-01D-CSP-{role}.md").write_text("\n".join(md), encoding="utf-8")
    print("[EVIDENCE] Redacted JSON/Markdown saved to ignored local directory; do not commit raw evidence.")


def main(argv: list[str] | None = None) -> int:
    args = parse_args(argv)
    print("REL-VERIFY-01D — staging CSP report-only shell smoke")
    try:
        origin = allowed_origin(args.base_url, args.ack_staging)
        outdir = evidence_directory(args.out_dir)
    except ValueError as exc:
        print("[STOP] " + str(exc), file=sys.stderr)
        return 2
    if args.run and not args.ack_staging:
        print("[STOP] Explicit --ack-staging required for all actual runs, including localhost.", file=sys.stderr)
        return 2
    if not args.run:
        print(f"[DRY RUN] Role={args.role}, GET paths={len(ROLE_PATHS[args.role])}, no browser/network/files touched.")
        print("[GATE] A-21/V-09 OPEN. Run only with --run --ack-staging on isolated production-mode staging.")
        return 0
    try:
        confirmed, rows = run_browser(origin, args.role, args.browser_path)
        status = classify(rows, confirmed)
        write_evidence(outdir, origin, args.role, status, confirmed, rows)
        print(f"[RESULT] {status}; role={args.role}; A-21/V-09 still OPEN pending full staging review.")
        return 0 if status == "CANDIDATE_SHELL_ONLY" else 2 if status.startswith("BLOCKED") else 1
    except Exception:
        print("[STOP] Browser or staging run failed; details redacted. No gate is accepted.", file=sys.stderr)
        return 2


if __name__ == "__main__":
    sys.exit(main())
