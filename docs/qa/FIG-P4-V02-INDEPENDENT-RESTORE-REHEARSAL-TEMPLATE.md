# FIG-P4 / V-02 — Independent Database + Private Media Restore Evidence Template

**Class:** operator-only REHEARSAL, not automated feature-test. **Baseline commit:** `de22f8f676bdb5bb76f6376d944322517bf23497`. **Status at delivery:** `BLOCKED / NOT YET PERFORMED`.

Do not commit filled reports, SQL dumps, authentication cookies, private file paths, original images, account secrets, or user data. Keep evidence inside ignored `.siapegawai-backup/` or an approved private evidence vault, redacting identifiers from any review excerpt.

## STOP checks before touching any restore target

- Source must be **synthetic testing/staging** and snapshot-consistent. Confirm `SELECT DATABASE()` and backup timestamp; do not use shared customer records.
- Restore MySQL target must be a separately authenticated, initially empty, explicitly labeled restore-only DB, with a **separate connection, root and port**. Never import a dump that contains `USE`, `CREATE DATABASE` or destructive commands aimed at the source without human inspection.
- `question_media_private` **and** `class_media_private` source roots and restored roots must be physically independent, non-nested and not symlinked. No public storage link.
- A verified SQL dump SHA-256 is recorded independently at backup time. Old published revision references and old pinned attempts remain present.
- Operators, permissions, PHP runtime, web host, replay policy, source/restore accounts and secure retention location approved. No automatic app restore by installer.

## V-02 execution register (operator fills privately)

| Step | Evidence / measured result | Reviewer | State |
|---|---|---|---|
| V02-A Snapshot source DB + two private roots at T0 | T0, internal environment IDs; aggregate row counts | — | OPEN |
| V02-B Dump checksum and physical copy inventory | external SHA-256; total bytes/count, source paths redacted | — | OPEN |
| V02-C Restore DB onto independent empty target | target DB name independently rechecked; SQL exit code | — | OPEN |
| V02-D Copy both private roots to independent target | checksum/bytes parity; no symlinks | — | OPEN |
| V02-E Source/restore DB schema+migrations+row-count compare | existing `SGP-REL-VERIFY-01C-DB-RESTORE-COMPARE.php` exit 0 | — | OPEN |
| V02-F Question version media reference→bytes audit | `siapegawai:fig-p4-media-audit` on source **and restored DB**; same digest; all objects exist | — | OPEN |
| V02-G Private roots parity | `SGP-FIG-P4-MEDIA-RESTORE-AUDIT.php` exit 0; both disks nonempty | — | OPEN |
| V02-H Restored app auth/phase smoke | QQ, CAT, Class active+completed+historical; foreign/guest/revoked 404 | — | OPEN |
| V02-I Fault injection **on disposable restored copy only** | missing/corrupt object => 404/no writes, repair from backup, smoke rerun | — | OPEN |
| V02-J Quantified recovery | T0, data loss window (measured RPO), actual recovery time (measured RTO), **owner-approved targets** | — | OPEN |

Run source and target Laravel audit **separately** in different isolated terminals with explicit `--expected-db=...`; use `--env=testing` and confirm actual `SELECT DATABASE()` prior to each. Schema/row parity alone does not prove blob parity; blob parity alone does not prove DB restore. Record command exit code, timestamp and output securely, not in public repository.

### Read-only media reference audit (test DB example)

```bat
cd /d E:\NewChapter1\project\siapegawai
set "APP_ENV=testing"
set "DB_DATABASE=siapegawai_testing"
php artisan siapegawai:fig-p4-media-audit --run --expected-db=siapegawai_testing --env=testing
```

For the independent restored app, override `DB_DATABASE` and private disk roots using an independently configured isolated restore runtime. The command accepts a named `siapegawai_*restore_verify_testing` database but **never creates it**.

### Independent four-root file parity (manual absolute paths required)

```bat
php tools\rel-verify\SGP-FIG-P4-MEDIA-RESTORE-AUDIT.php --help
```

Actual `--run` needs dump path, dump SHA independently recorded at T0, `--source-question`, `--restored-question`, `--source-class`, `--restored-class`; it **never copies** data. First execute against synthetic test roots and check a deliberate corruption returns exit code 2. If Class has no private PDF fixture, tool returns BLOCKED rather than false PASS.

### Review and gate

- Evidence collected by: ______ ; independent reviewer: ______ ; measured RTO: ______ ; measured RPO: ______ ; approved target: ______ .
- Result: `PASS / FAIL / BLOCKED` (initially BLOCKED).
- **Never** mark `V-02 PASS` without independent actual restore and authenticated GET smoke. A valid backup alone is insufficient. `REL-RECOVERY-01` remains a separate global release gate.
