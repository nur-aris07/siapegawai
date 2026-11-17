# SEO-PUBLIC-01 — Production activation and HTTP evidence register

**Status: NOT ACTIVATED / OPEN.** This document is a checklist, not a release authorization.

## Deployment policy — owner must approve every item

- [ ] All `REL-CSP-STAGING-01`, `REL-RECOVERY-01`, `REL-OPS-LEGAL-UAT-01` release gates are addressed independently; this milestone never implies GO-LIVE.
- [ ] Owner signs off the actual HTTPS canonical origin and www/non-www alias strategy; TLS, proxy, redirects, HSTS, and CDN tested outside the application.
- [ ] Owner validates copy/availability and approves each public route separately: `home`, `questions.index`, `practice.intro`, `page.kelas`, `page.paket`; `page.tentang` and `page.kontak` remain opt-in. **Do not** approve legal placeholders.
- [ ] Only then update `config/seo.php` `approved_indexable_routes` to the explicitly approved named routes and set the deployment-only `SIAPEGAWAI_SEO_CANONICAL_ORIGIN=https://<verified-domain>` and `SIAPEGAWAI_SEO_INDEXING_ENABLED=true`; build/cache config via owner operations (not installer). Never store this in `site_settings/custom_*`.
- [ ] Inspect actual externally observed HTTP 200 HTML response on canonical origin: single title/description/canonical, matching H1 and useful visible main content, `lang=id`, no meta/header `noindex`, no redirect or authentication barrier.
- [ ] Inspect internal anchors from `/` to approved URLs, then from other public pages, with JS disabled: valid `<a href>`, crawlability, no broken links, appropriate anchor text.
- [ ] Verify query behavior for `/bank-soal`: tracking and valid category variants canonical to base; nonempty `cari`, invalid category, unknown params and session/token inputs remain NOINDEX, not in sitemap.
- [ ] Inspect `GET /sitemap.xml`: absolute HTTPS URLs, only approved pages, no query/private/session/legal; each URL returns 200 HTML, matching self canonical, no noindex, no redirect.
- [ ] Inspect actual static `GET /robots.txt` served by web server; does not block approved HTML or public NOINDEX pages; do not use robots.txt as authorization.
- [ ] Check `X-Robots-Tag`, cache/CDN, Host and X-Forwarded-Host spoofing, HTTPS scheme as observed by Laravel/proxy, aliases and stale cache response; no conflicting robots directives.
- [ ] Verify login/account, participant/admin, private media and legal placeholder remain protected or NOINDEX as appropriate. Log no sensitive content/PII in SEO output.
- [ ] When and only when owner authorizes production release, verify the **public hostname** HTTP evidence again and then register Search Console / submit sitemap; monitor coverage, Google-selected canonical, excluded pages, impressions, search clicks. No ranking or indexing guarantee.

## Per-route evidence template (fill using owner-observed responses)

| route | URL | HTTP | meta robots | X-Robots-Tag | canonical | title/description | H1/lang | link referrer | sitemap | content approval | reviewer | result |
|---|---|---|---|---|---|---|---|---|---|---|---|
| home | NOT TESTED | - | - | - | - | - | - | - | - | pending | - | BLOCKED |
| questions.index | NOT TESTED | - | - | - | - | - | - | - | - | pending | - | BLOCKED |
| practice.intro | NOT TESTED | - | - | - | - | - | - | - | - | pending | - | BLOCKED |
| page.kelas | NOT TESTED | - | - | - | - | - | - | - | - | pending | - | BLOCKED |
| page.paket | NOT TESTED | - | - | - | - | - | - | - | - | pending | - | BLOCKED |

**Known legal gate:** `/syarat-layanan` contains historical wording suggesting manual activation for all registrations, inconsistent with the already-approved free-active registration policy. Keep legal pages NOINDEX; do not silently edit legal copy in this SEO milestone.
