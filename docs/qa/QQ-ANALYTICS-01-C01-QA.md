# QQ-ANALYTICS-01 — C-01 implementation and QA contract

**Approved work ID:** `QQ-ANALYTICS-01` · **Baseline:** `main` `dc0365f68c90c0f6d96a6f269a886efbe9c041ad` · **Phase:** C, candidate only. No owner acceptance or Git publication is claimed here.

## Implemented scope

- New **GET `/quiz-kilat/analisis`**, route name `member.quick-quizzes.analytics`, under the existing `auth + EnsureActiveAccount + participant` middleware. The route is declared **before** catch-all `/{quiz}`.
- Read-only `QuickQuizAnalyticsService`: every query scopes **`user_id` of the authenticated participant** and **`status = completed`**; no CAT/class joins, quota consumption, writes, migrations, login automation, or background jobs.
- Counts from **locked** answer rows: `answered + is_correct` = correct; `answered + !is_correct` = wrong; `timeout` = timed out; no unissued question treated as a wrong answer. Accuracy denominator is **all locked rows in completed sessions**, including timeouts. Session mean and best score use persisted, server-scored `score_percent`; answered response average excludes timeout rows, displayed in seconds.
- Category and topic groupings use **`quick_quiz_version_items.*_snapshot`** only; master category/topic changes cannot rewrite history. A recent trend includes **up to 8 completed sessions** in chronological order. An empty state explains excluded in-progress and abandoned sessions.
- Entry links from QQ catalogue and QQ history; responsive view uses existing workspace layout plus scoped CSS. All snapshot names and titles are Blade-escaped. C-01 initially used no JavaScript; C-03 adds a local Vite-bundled progressive table enhancement with no inline scripts, external CDN, or CSP middleware changes.

## Acceptance checklist (not yet independently run on owner laptop)

| AC | Verify |
|---|---|
| QQ-AN-01 | Active participant can access own analysis from catalogue/history |
| QQ-AN-02 | Correct/wrong/timeout, accuracy and answered-only average time match controlled fixtures |
| QQ-AN-03 | Active/abandoned attempts do not enter final metrics, groups or trend |
| QQ-AN-04 | Snapshot category/topic retains historical names after master edits |
| QQ-AN-05 | Guest redirects; other participant data excluded; inactive participant/admin forbidden; dynamic content escaped |
| QQ-AN-06 | Empty state, keyboard navigation, mobile table overflow and recent trend reviewed in browser |
| QQ-AN-07 | Focused PHPUnit, full PHPUnit, Vite production build, route verification and relevant owner UAT PASS |

## Commands and evidence

Run installer `--dry-run` (default), `--apply`, `--verify`, lint, `php artisan optimize:clear`, focused PHPUnit, full PHPUnit, `npm run build` (if Node dependencies already installed) and browser UAT. Never run migrations against the active app DB for this milestone. PHPUnit database should remain `siapegawai_testing`.

Record exact counts, regressions, view screenshots for narrow/mobile, empty state, response metrics and role boundaries; no password, cookie or private session dumps. Any failed check returns to Phase C and installer revision. Only explicit `OWNER-ACCEPTED` permits Phase E; owner executes Git commands themselves. Do not stage `siapegawai-update.php`, `.env.siapegawai.example`, `__pycache__`, local backup or the previously preserved unreviewed REL-VERIFY-01D QA register.

**Release P0 inherited from REL-VERIFY-01D remains OPEN:** independent restore and actual-media parity, real RTO/RPO, staged CSP review and release sign-off. This feature milestone cannot declare production readiness.

## C-02 — Focused PHPUnit assertion correction (2026-11-14)

The first owner-run focused PHPUnit returned **3 passed, 1 failed**. The failing assertion was a PHP type mismatch in `test_completed_analytics_totals_response_average_and_snapshot_grouping`: `round((float) $summary['average_response_ms']) === 2000` compares a PHP **float** to an **integer** using strict equality. PHP returns a float from `round()`, so a correct 2,000 ms average falsely failed this closure.

C-02 changes only the test expectation to `=== 2000.0` (float-to-float). It does **not** modify analytics calculation, scoring, auth, database, schema, views or routes; all numerical acceptance conditions remain the same. PHP CLI reproduced the original strict-type false and corrected comparison true. The owner must rerun focused tests, full PHPUnit and Vite; status **PENDING owner execution**, not yet PASS.


## C-03 — Dashboard UX revision (owner feedback 2026-11-14)

Owner reviewed populated catalogue and analysis screenshots. C-02 computation and security tests passed (174 tests / 1450 assertions), but owner requested: **compact sortable/paged category/topic details**, **a visual dashboard/summary**, and **clear, measured spacing for links and controls**.

C-03 implements a two-column visualization (answer mix and last-eight-session line trend) rendered as CSP-safe server-side SVG; four KPI tiles and a concise category-accuracy overview. Long category/topic tables are moved into collapsible `details`; a local Vite JS entry progressively enables client-side search, sortable headers, 5/10/25-row pagination and accessible result counts. This is DataTables-like *behavior*, not the third-party DataTables dependency. Without JS, tables remain accessible as complete server-rendered HTML. No remote assets, inline JS, DB write, migration, quiz scoring, quota, or service query change. Existing participant route and isolation remain as in C-02.

**C-03 QA required:** installer `--verify` 11/11; focused PHPUnit; full PHPUnit; Vite production build; `public/build/manifest.json` must include `resources/js/member-qq-analytics.js`; browser: 390px/mobile and desktop, chart labels, disclosure open/close, 5/10/25 pagination, search, sort ascending/descending on numeric and text columns, next/previous, no-result state, keyboard access, empty-state and zero-CSP/JS errors in observed smoke. Security role isolation follows C-02 tests. QA screenshots, visual acceptance and final gate await OWNER. Do not stage/release before OWNER-ACCEPTED.
