#!/usr/bin/env python3
"""REL-VERIFY-01 public-only, unauthenticated Playwright screenshot + overflow smoke.

Default is dry-run. Requires playwright and browser installed. Does not log in,
modify data, submit forms, or follow off-origin links.
"""
import argparse
import datetime
import json
import pathlib
import shutil
import sys
import urllib.parse

PUBLIC_PATHS = ["/", "/bank-soal", "/latihan-gratis", "/kelas", "/paket", "/privasi", "/syarat-layanan"]
WIDTHS = [320, 375, 768, 1024, 1440]


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--run", action="store_true", help="Perform headless Chromium GET probes and screenshots")
    parser.add_argument("--base-url", default="http://127.0.0.1:8000", help="Loopback http URL only")
    parser.add_argument("--out-dir", default=".siapegawai-backup/rel-verify-01-browser", help="Evidence directory")
    args = parser.parse_args()
    parsed = urllib.parse.urlparse(args.base_url)
    if parsed.scheme != "http" or parsed.hostname not in ("127.0.0.1", "localhost", "::1") or parsed.username or parsed.password or parsed.query or parsed.fragment or parsed.path not in ("", "/"):
        parser.error("--base-url must be loopback HTTP without path, query, or credentials")
    base = args.base_url.rstrip("/")
    print("[SCOPE] Public GET pages only, widths:", WIDTHS, "paths:", PUBLIC_PATHS)
    print("[LIMIT] No authenticated/admin screens; 200% browser zoom and a11y remain manual")
    if not args.run:
        print("[DRY RUN] No screenshots or requests. Add --run.")
        return 0

    from playwright.sync_api import sync_playwright

    target = pathlib.Path(args.out_dir).resolve()
    target.mkdir(mode=0o700, parents=True, exist_ok=True)
    results = []
    with sync_playwright() as pw:
        # Prefer an installed full Chromium binary when Playwright's optional headless-shell
        # artifact is absent; otherwise let Playwright use its default bundled browser.
        browser_opts = {"headless": True}
        full_chromium = pw.chromium.executable_path if pathlib.Path(pw.chromium.executable_path).is_file() else (shutil.which("chromium") or shutil.which("chromium-browser") or shutil.which("google-chrome"))
        if full_chromium:
            browser_opts["executable_path"] = full_chromium
        try:
            browser = pw.chromium.launch(**browser_opts)
        except Exception as exc:
            print(f"[BLOCKED] Chromium cannot launch: {type(exc).__name__}. Install Playwright Chromium or use a compatible local browser.")
            return 2
        try:
            for path in PUBLIC_PATHS:
                for width in WIDTHS:
                    context = browser.new_context(viewport={"width": width, "height": 850}, service_workers="block")
                    # All outgoing non-loopback requests blocked; no auth/stateful network calls.
                    context.route("**/*", lambda route: route.continue_() if urllib.parse.urlparse(route.request.url).hostname in ("127.0.0.1", "localhost", "::1") else route.abort())
                    page = context.new_page()
                    entry = {"path": path, "viewport": width, "status": "BLOCKED", "horizontal_overflow_px": None, "screenshot": None, "notes": []}
                    try:
                        res = page.goto(base + path, wait_until="domcontentloaded", timeout=12000)
                        page.wait_for_timeout(300)
                        status = res.status if res else None
                        entry["http_code"] = status
                        if status != 200:
                            entry["status"] = "FLAG" if status else "BLOCKED"
                            entry["notes"].append("Expected public HTTP 200; investigate response")
                        else:
                            data = page.evaluate("""() => ({
                               width: document.documentElement.clientWidth,
                               scroll: document.documentElement.scrollWidth,
                               title: document.title,
                               lang: document.documentElement.lang,
                               headings: document.querySelectorAll('h1').length,
                               imagesWithoutAlt: [...document.querySelectorAll('img')].filter(i => !i.hasAttribute('alt')).length
                            })""")
                            overflow = max(0, data["scroll"] - data["width"])
                            entry["horizontal_overflow_px"] = overflow
                            entry["h1_count"] = data["headings"]
                            entry["document_language_present"] = bool(data["lang"])
                            entry["images_missing_alt_attribute"] = data["imagesWithoutAlt"]
                            entry["status"] = "PASS" if overflow <= 2 and data["headings"] >= 1 and data["lang"] and data["imagesWithoutAlt"] == 0 else "FLAG"
                            if overflow > 2: entry["notes"].append(f"Horizontal overflow {overflow}px")
                            if data["headings"] != 1: entry["notes"].append(f"{data['headings']} h1 elements; review hierarchy")
                            if not data["lang"]: entry["notes"].append("Missing document language")
                            if data["imagesWithoutAlt"]: entry["notes"].append("Images missing alt attribute")
                            safe = "home" if path == "/" else path.strip("/").replace("/", "-")
                            filename = f"{safe}-{width}.png"
                            page.screenshot(path=str(target / filename), full_page=True, animations="disabled")
                            entry["screenshot"] = filename
                    except Exception as exc:
                        entry["status"] = "BLOCKED"
                        entry["notes"].append(f"Request/browser failed: {type(exc).__name__}; inspect server")
                    finally:
                        results.append(entry)
                        print(f"[{entry['status']}] {path} {width}px — overflow: {entry['horizontal_overflow_px']}")
                        context.close()
        finally:
            browser.close()
    evidence = {
        "tool": "SGP-REL-VERIFY-01-PUBLIC-BROWSER-SMOKE/1.0",
        "generated_utc": datetime.datetime.now(datetime.timezone.utc).isoformat(),
        "base_url": base,
        "scope": "Public GET only; does not assess authenticated pages, zoom 200%, CSP violations, actual keyboard use, or screenshots visually",
        "results": results,
    }
    (target / "REL-VERIFY-01-PUBLIC-BROWSER.json").write_text(json.dumps(evidence, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    return 1 if any(x["status"] != "PASS" for x in results) else 0


if __name__ == "__main__":
    sys.exit(main())
