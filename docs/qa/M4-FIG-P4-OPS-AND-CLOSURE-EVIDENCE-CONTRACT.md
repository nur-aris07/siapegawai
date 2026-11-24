# M4 FIG-P4 Operational evidence and closure — execution contract

**Work:** `C-PILOT-OPS-FIG-01` · **Published parent HEAD:** `cb4d9b72035213fca3ba9281fa38ed3c3fcf9f12` · **Owner review:** pending. This document is technical implementation support, **not** a final FIG/V02/V03/GP acceptance certificate.

## Evidence reuse and bounded new controls

- Existing accepted FIG-P1/P2/P3, P4-E01 editorial, PR03 media compatibility, pilot access and learning-ops source are **not** reimplemented. Reuse original immutable QA evidence. The last native baseline run (`bbfc460b6dc9`) reported 378 PHPUnit tests/3,578 assertions, 41 legacy FIG tests, Vite/Blade, Chrome 30-item QQ, and native concurrency PASS. Never mislabel those numbers as results of this new run.
- **E02/PDF closure:** the Class private PDF GET must verify stored metadata SHA-256, byte size, PDF magic and local path confinement *before* download; missing/corrupt/private-path/foreign/unenrolled GET returns 404 or existing denied contract, without DB writes or silent repairs. New `SiapegawaiFigP4OpsClosureTest` exercises the complete downloadable PDF fixture and synthetic corrupted-then-restored bytes. Existing `QuestionMediaContextAccess` continues to control QQ/CAT/Class question images by phase and ownership.
- **E03/V02:** the installer performs synthetic file-copy/corruption/recovery checks on its own evidence directory, **not** an independent database restore. An independently owned second database endpoint, source/target isolated SQL credentials, valid consistent dump, both private roots and authenticated restored-app smoke are missing; report `BLOCKED_NO_INDEPENDENT_TARGET`. Never claim `PASS_V02` from hash parity alone or silently treat port 3310 as disposable.
- **E04/V03:** the Chrome UAT samples actual participant DOM image decode and server `Date` header with send/receive timing. The pure Node verifier reports a conservative *interval* for server deadline remaining, using ±1,000 ms bound for Date header granularity and measured round trip; image visibility and negative time are separate signals. This measurement does **not** independently observe server issuance, prove image legibility, cover slow/physical network profiles, or adopt a numeric fairness threshold. `V03_FINAL_PASS` is impossible from this synthetic trace alone.
- **E05:** `FigP4OperationalEvidence::reconcile` produces exactly `AC-FIG-01..12`; prior accepted proof is reused, new local proof is labeled synthetic/partial, and AC11/12, GP02, GP03/04/05 and GP06 remain explicitly OPEN/HOLD. No automatic release flags are changed.

## Genuine blockers for final field acceptance

| Gate | Missing proof | Current decision |
|---|---|---|
| P4-E02/AC06..09 | Expiry/reconnect matrices, representative CAT/Class images on target, physical PDF UAT | Partial synthetic only; do not infer complete matrix |
| V02/AC12/GP04 | Independently authorized restored DB endpoint and separate filesystem, actual restored app login/media, measured RTO/RPO and signed targets | `BLOCKED_NO_INDEPENDENT_TARGET` |
| V03/AC11/GP05 | Actual phones/networks and approved numeric usable-window policy, latency/clock uncertainty and reviewer signoff | `OPEN_REAL_DEVICE_AND_POLICY` |
| GP02 | Original 30 question / 59 asset pack, academic and media-rights review signatures | `OPEN_HUMAN_ACADEMIC_AND_MEDIA_RIGHTS` |
| GP06 | Final owner pilot GO | `HOLD` |

## Native operator contract

Use ordinary CMD or PowerShell (not Administrator by default). Place a single embedded `siapegawai-update.php` at project root and run `--dry-run`, `--run`, then `--verify` if source applied. The installer checks exact HEAD and source SHA before writing; stages an isolated candidate from the bundle, executes PHPUnit, Vite, Node unit tests and actual Chrome UAT using distinct *run-owned* QA schema names on MariaDB port 3309; source transaction is atomic with original bytes preserved. Evidence/backups are only under `.siapegawai-backup/m4-pilot-ops-fig-closure/<run-id>/`. Other existing QA schemas, `.env`, 3307/3308/3310 and real participants are not disposable. Do not run `migrate:fresh` outside a verified run-owned isolated testing schema.

No Git operation is embedded. **Fase D is manual Git commands by Owner only** and only after technical acceptance, with explicit added/updated file paths, multi-`-m` commit message and no `git add .`.

## Interpreting reports

- `PASS` for a mandatory stage means that specific synthetic QA test passed, not that an external gate closed.
- `INCONCLUSIVE_CLOCK_OR_CAPTURE` is not a benchmark success; keep V03 OPEN.
- `BLOCKED_NO_INDEPENDENT_TARGET` is not an internal code defect; do not provision another MariaDB instance without separate scoped owner approval of its ownership and operations.
- `QA_PASS_SOURCE_APPLIED_AWAITING_OWNER_ACCEPTANCE` can coexist with P4/GP external statuses OPEN.
- Shared `SHARE-SUMMARY.json` must contain no raw SQL, private URLs or paths, credentials, candidate question text/answer keys, or participant PII.
