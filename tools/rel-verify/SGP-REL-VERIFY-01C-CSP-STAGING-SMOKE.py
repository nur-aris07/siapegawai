#!/usr/bin/env python3
"""REL-VERIFY-01C / v1.15. Guest-only Chromium CSP report-only evidence.

No login, cookies, request bodies or production interactions. No screenshot or
console-message capture. Never declares full A-21/V-09 sign-off.
"""
from __future__ import annotations

import argparse
import datetime as dt
import hashlib
import ipaddress
import json
import os
from pathlib import Path
import re
import sys
from urllib.parse import urlparse

PATHS = ("/", "/bank-soal", "/latihan-gratis", "/kelas", "/paket", "/privasi", "/syarat-layanan")
SCRIPT_DIR = Path(__file__).resolve().parent
PROJECT_ROOT = SCRIPT_DIR.parent.parent
EVIDENCE_ROOT = PROJECT_ROOT / ".siapegawai-backup" / "rel-verify-01c"


def cli() -> argparse.Namespace:
    p = argparse.ArgumentParser(description="Siapegawai read-only CSP report-only browser smoke (public pages only)")
    p.add_argument("--run", action="store_true", help="visit the explicitly specified staging origin")
    p.add_argument("--base-url", default="http://127.0.0.1:8000")
    p.add_argument("--ack-staging", action="store_true", help="affirm a remote HTTPS staging/QA host is isolated from production")
    p.add_argument("--out-dir", default=".siapegawai-backup/rel-verify-01c/csp")
    p.add_argument("--browser-path", help="optional local Chromium executable path")
    return p.parse_args()


def allowed_origin(base: str, ack_staging: bool) -> str:
    u = urlparse(base)
    if (u.scheme not in ("http", "https") or not u.hostname or u.username or u.password or u.query or u.fragment or u.path not in ("", "/")):
        raise ValueError("Provide only a staging origin (scheme://host[:port]), no credentials or path")
    if u.hostname in ("localhost", "127.0.0.1", "::1"):
        if u.scheme != "http" and u.scheme != "https":
            raise ValueError("Unsupported localhost protocol")
        return base.rstrip("/")
    try:
        address = ipaddress.ip_address(u.hostname)
        if address.is_private and ack_staging and u.scheme == "https":
            return base.rstrip("/")
    except ValueError:
        pass
    host = u.hostname.lower()
    # Never allow a remote production hostname by accident.
    if not ack_staging or u.scheme != "https" or not re.search(r"(^|[.-])(staging|stage|qa)([.-]|$)", host):
        raise ValueError("Remote URL must be an explicit HTTPS staging/QA hostname and require --ack-staging")
    return base.rstrip("/")


def evidence_dir(argument: str) -> Path:
    if "\x00" in argument or ".." in Path(argument).parts:
        raise ValueError("Unsafe evidence path")
    target = Path(argument)
    if not target.is_absolute():
        target = PROJECT_ROOT / target
    # Resolve rather than follow a symlink out of the ignored tree.
    target = target.resolve()
    base = EVIDENCE_ROOT.resolve()
    if target == base or base not in target.parents:
        raise ValueError("Evidence must stay inside .siapegawai-backup/rel-verify-01c/")
    # Ensure user-provided output itself isn't a symlink to elsewhere.
    if Path(argument).is_symlink():
        raise ValueError("Evidence symlink path forbidden")
    return target


def sanitize_directives(raw: dict) -> dict:
    result = {}
    for key, value in raw.items():
        label = str(key)[:50]
        if re.fullmatch(r"[a-zA-Z0-9-]+", label):
            result[label] = max(0, int(value))
    return result


def run_browser(origin: str, browser_path: str | None) -> list[dict]:
    from playwright.sync_api import sync_playwright

    rows = []
    with sync_playwright() as p:
        opts = {"headless": True}
        if browser_path:
            exe = Path(browser_path)
            if not exe.is_file():
                raise RuntimeError("--browser-path executable not found")
            opts["executable_path"] = str(exe)
        browser = p.chromium.launch(**opts)
        context = browser.new_context(ignore_https_errors=False, service_workers="block", java_script_enabled=True)
        context.add_init_script("""
            (() => {
              window.__sgpCspCounts = Object.create(null);
              document.addEventListener('securitypolicyviolation', (event) => {
                const directive = String(event.effectiveDirective || event.violatedDirective || 'unknown').substring(0, 50);
                if (/^[a-zA-Z0-9-]+$/.test(directive)) {
                  window.__sgpCspCounts[directive] = (window.__sgpCspCounts[directive] || 0) + 1;
                }
              });
            })();
        """)
        try:
            for path in PATHS:
                page = context.new_page()
                # Request and browser console URLs can contain private data. We do not retain them.
                row = {"path": path, "http_status": None, "csp_report_only_present": False,
                       "csp_directives": [], "enforce_header_present": False,
                       "violation_counts_by_directive": {}, "navigation_error": False}
                try:
                    response = page.goto(origin + path, wait_until="domcontentloaded", timeout=15000)
                    row["http_status"] = response.status if response else None
                    if response:
                        headers = {k.lower(): v for k,v in response.headers.items()}
                        raw = headers.get("content-security-policy-report-only", "")
                        row["csp_report_only_present"] = bool(raw.strip())
                        # Store directive names only, NEVER raw policies or report URIs.
                        row["csp_directives"] = sorted({
                            v.strip().split()[0].lower() for v in raw.split(";")
                            if v.strip() and re.fullmatch(r"[a-zA-Z-]+", v.strip().split()[0])
                        })
                        row["enforce_header_present"] = bool(headers.get("content-security-policy", "").strip())
                    page.wait_for_timeout(900)
                    raw_counts = page.evaluate("Object.assign({}, window.__sgpCspCounts || {})")
                    if isinstance(raw_counts, dict):
                        row["violation_counts_by_directive"] = sanitize_directives(raw_counts)
                except Exception:
                    row["navigation_error"] = True
                finally:
                    page.close()
                rows.append(row)
                count = sum(row["violation_counts_by_directive"].values())
                print(f"[CHECK] {path}: HTTP {row['http_status']}, report-only={row['csp_report_only_present']}, violations={count}, error={row['navigation_error']}")
        finally:
            context.close()
            browser.close()
    return rows


def classify(rows: list[dict]) -> str:
    if not any(r["csp_report_only_present"] for r in rows):
        return "BLOCKED_NO_REPORT_ONLY_HEADER"
    if any(r["navigation_error"] or r["http_status"] != 200 or
           not r["csp_report_only_present"] or r["enforce_header_present"] or
           sum(r["violation_counts_by_directive"].values()) for r in rows):
        return "FLAG_REQUIRES_REVIEW"
    return "CANDIDATE_PUBLIC_ONLY"


def write_evidence(outdir: Path, origin: str, status: str, rows: list[dict]) -> None:
    outdir.mkdir(parents=True, exist_ok=True)
    result = {
        "tool": "REL-VERIFY-01C v1.15",
        "generated_wib": dt.datetime.now(dt.timezone(dt.timedelta(hours=7))).isoformat(),
        "origin_sha256_prefix": hashlib.sha256(origin.encode()).hexdigest()[:12],
        "status": status,
        "scope": "seven anonymous public GET pages only; no authentication or site-wide sign-off",
        "paths": rows,
        "gate_a21": "OPEN",
        "gate_v09": "OPEN",
        "limitations": "Report-only violation events and headers only. Login, admin/member, Yajra, AJAX QQ, console details, proxy headers and policy enforcement require manual staging UAT.",
    }
    (outdir / "REL-VERIFY-01C-CSP-PUBLIC.json").write_text(json.dumps(result, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    lines = ["# REL-VERIFY-01C — Public CSP Staging Smoke", "",
             "> Observational evidence only; **A-21/V-09 remain OPEN** until authenticated staging coverage and sign-off.", "",
             f"- Status: **{status}**", "- Scope: 7 public routes; no credentials or session used", "",
             "| Path | HTTP | Report-only header | Violation events | Enforce header |", "|---|---:|---|---:|---|"]
    for r in rows:
        lines.append(f"| `{r['path']}` | {r['http_status'] or 'ERROR'} | {'yes' if r['csp_report_only_present'] else 'no'} | {sum(r['violation_counts_by_directive'].values())} | {'yes' if r['enforce_header_present'] else 'no'} |")
    lines.extend(["", "**Unverified:** authenticated shells, CSP reports from all user flows, enforcement decision, production proxy, accessibility, independent DB restore.", ""])
    (outdir / "REL-VERIFY-01C-CSP-PUBLIC.md").write_text("\n".join(lines), encoding="utf-8")
    print("[EVIDENCE] Redacted JSON/Markdown written under ignored rel-verify-01c directory.")


def main() -> int:
    args = cli()
    print("REL-VERIFY-01C / v1.15 — Public CSP browser smoke (report-only)")
    try:
        origin = allowed_origin(args.base_url, args.ack_staging)
        outdir = evidence_dir(args.out_dir)
    except ValueError as e:
        print("[STOP] " + str(e), file=sys.stderr)
        return 2
    if not args.run:
        print("[DRY RUN] No requests or files written; scope 7 anonymous routes. Use --run on isolated production-mode staging.")
        return 0
    try:
        rows = run_browser(origin, args.browser_path)
        status = classify(rows)
        write_evidence(outdir, origin, status, rows)
        print(f"[RESULT] {status}; 7 public routes checked. No authenticated coverage.")
        print("[GATE] A-21/V-09 OPEN until full staging CSP review and reviewer sign-off.")
        return 0 if status == "CANDIDATE_PUBLIC_ONLY" else 2 if status.startswith("BLOCKED") else 1
    except Exception as e:
        print("[STOP] Browser CSP evidence could not be collected (browser/network/config). Details redacted; see local environment.", file=sys.stderr)
        return 2


if __name__ == "__main__":
    sys.exit(main())
