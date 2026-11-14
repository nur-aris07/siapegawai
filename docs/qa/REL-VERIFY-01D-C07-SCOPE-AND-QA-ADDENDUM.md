# REL-VERIFY-01D — C-07 Scope and QA Addendum (14 November 2026 WIB)

**Work ID:** `REL-VERIFY-01D` · **Baseline:** `main` at `c304e1c948d01502ebf59a316fb90b88743fa6b2` at C-07 planning · **Status:** QA record / `OWNER-ACCEPTED` pending. This is a **separate, additive document** because C-06 detected an unexpected SHA-256 in `docs/qa/REL-VERIFY-01D-AUTH-CSP-AND-RESTORE-EVIDENCE.md`. The existing register is **preserved without overwrite**, and its actual contents have **not** been reviewed by the AI. Its status must be checked in Git audit before any stage/commit. C-07 does not authorize deleting it.

## 1. Approved scope correction; no release gate is waived

The owner agreed to conclude **development toolkit and local verification** under the current work ID, not to certify an isolated staging/production release. Earlier requirement to demonstrate independent staging recovery, actual media backup parity, whole-process RTO/RPO, and reviewed CSP staging **remains OPEN as mandatory P0 release verification**; it is **not** mislabeled PASS. The owner has **not yet** explicitly stated `OWNER-ACCEPTED` for the revised milestone. No Git completion has been claimed.

## 2. Evidence actually reported (historical, not re-run by this addendum)

| ID | Evidence recorded by owner/operator as of 14 November 2026 | Scope/status |
|---|---|---|
| DEV-01 | Installers C-01 through C-04 reported apply/verify; C-04 `5/5 hashes`. C-06 dry-run found **CONFLICT** for the original QA register; **no C-06 changes** were applied. The C-07 installer must complete its own apply/verify/idempotency. | **PENDING C-07** |
| DEV-02 | Authenticated CSP smoke, A-25 rehearsal, restored-app feature test and CSP interaction observer installed. C-04 observer self-test passed; user's C-06 log shows observer hash already equals the fixed tool (`122a31582c69c7fea846d0f523710469360f0589ccb46c33d662a76bd0fba83b`), so C-07 does not overwrite it. | **PASS for installed tooling; C-07 final verify pending** |
| DEV-03 | Public/account/member/admin GET-shell CSP smoke total **7+3+5+4 = 19/19**, all HTTP 200, CSP Report-Only found, no violations **within those completed smoke checks**. Achieved using temporary `APP_ENV=production` in local QA; not independent staging. | **PASS — local smoke only** |
| DEV-04 | Owner confirmed UAT-01..07, QQ AJAX, locked answers, feedback **at least 5 seconds**, admin DataTables search/pagination. C-04 session logs included member **80** AJAX requests and admin **7**, with 0 captured CSP events and 0 JS errors in those completed sessions. However, both tool sessions printed `BLOCKED_MANUAL_REVIEW_NOT_CONFIRMED`; one separately failed admin landing printed **2 CSP events**. Do **not** claim universal zero violations or complete staging CSP approval. | **PASS — owner-reported functional behavior**; staging CSP review OPEN |
| DEV-05 | Disposable MariaDB Native 10.4 logical restore: source/target **45/45 tables**, **20/20 migrations**, metadata, FK, index, row-count and synthetic probe content all matching; synthetic private-PDF parity passed; SQL-only import **0.796s**. Evidence status: `PASS_SYNTHETIC_LOGICAL_REHEARSAL_ONLY`. | **PASS — synthetic rehearsal only** |
| DEV-06 | Feature tests against disposable restored DB: **2 tests / 18 assertions PASS**; full PHPUnit on configured testing DB: **170 tests / 1,415 assertions PASS**. | **PASS — automated test coverage only** |
| DEV-07 | Explicit owner acceptance of *revised local-development milestone*, followed by verified Git commit/push. | **PENDING — not automatically granted** |

## 3. Release blockers retained and expressly unverified

- **A-25 / independent recovery:** real representative isolated test-data backup and restore, actual backed-up private-media parity, separate recovered application browser UAT, whole-process RTO/RPO observation, reviewer sign-off. Synthetic SQL rehearsal and storage-fake PDF tests do **not** replace these.
- **A-21/V-09 / isolated staging CSP:** independent production-build staging, interactive login/QQ/DataTables/PDF review under report-only CSP; investigate **2 browser CSP events** in one failed admin landing attempt; reviewer sign-off. Local 19-route smoke alone is not staging acceptance.
- **Other release requirements:** responsive/a11y, legal/privacy/WhatsApp, operational and production sign-offs stay OPEN according to the historical acceptance register and roadmap.

No `PRODUCTION-READY`, `A-25 release PASS`, `A-21 staging PASS`, or Git-published claim is made here. Future release work ID/scope needs its own B approval after E is verified.

## 4. C-07 installer, conflict protection and Git boundary

- C-07 intentionally **does not update** `docs/qa/REL-VERIFY-01D-AUTH-CSP-AND-RESTORE-EVIDENCE.md`; its content in the operator's checkout differs from the two known C-06 hashes. The operator must preserve it and inspect its diff/content at **E** if proposed for Git. Do not delete, replace or stage it blindly.
- C-07 installs this standalone addendum as **ADD only**, with SHA-256 conflict refusal if its destination already exists with unknown content. Four known toolkit/test files are exact-hash **SKIP**; no application migrations or DB operations are performed.
- Installer dry-run, `--apply`, `--verify`, idempotency, PHP syntax and Git status need operator output. No PHPUnit/restore/browser rerun is required solely for this documentation addition unless the accepted scope changes.
- Keep `siapegawai-update.php`, `.env`, `.env.siapegawai.example` (until specifically reviewed), dumps, private media, local evidence and backup folders out of Git. `OWNER-ACCEPTED` is a separate explicit decision after the C-07 results, **before** starting E.
