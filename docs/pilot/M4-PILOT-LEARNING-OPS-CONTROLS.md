# SIAPEGAWAI M4 — Pilot Learning Ops (Technical Controls)

Scope: **private local synthetic readiness only**; pilot/public/SEO/sales GO remains HOLD.

## Content QC

`php artisan pilot:content-qc --template` builds a private, **unsigned** 30-row register under `storage/app/private/pilot-learning-ops/` in the *candidate QA workspace*. It does not query or modify the DB. For scanning a pinned, published 30-item edition in an **isolated run-owned QA schema only**, use `--edition=<version_id>` (not supported on the live/main DB). No command can publish content or grant academic approval. For a template preview without private file writes use `--template --dry-run`.

Blueprint: pattern/sequence 8, analogy 6, classification 6, matrix 5, text reasoning 5. Academic criteria and rights require independent human reviewer, author provenance, exact-version digest binding, and Founder signoff. Technical structural/media checks do **not** substitute for these. A version change requires fresh human review. The inherited counts 30 candidates/59 media/120 options are unverified until original assets are available.

## Participant support

Only authorized pilot participants see the FAQ on the existing restricted dashboard. Fallback contact is `siapegawai@gmail.com`. No new ticket database, outbound email, automatic SLA, new public route, or extra participant PII. Use the blank **private** incident ledger under the installer's `.siapegawai-backup/m4-pilot-learning-ops/<run-id>/` folder. Never put populated incident rows in Git or share-safe summaries.

Incident categories: `content_accuracy`, `image_readability`, `timing_connection`, `account_access`, `privacy_request`, `other`. Critical security/academic issue: pause **new** starts via the existing approved pilot control, preserve lawful history, escalate to Owner/Tech/Academic. No mass revoke/delete by the support automation.

## Measurement

`php artisan pilot:measure --edition=<id> --cohort=QA-... --start=YYYY-MM-DDTHH:mm:ssZ --end=<start+7days> --cutoff=YYYY-MM-DDTHH:mm:ssZ --population=operational` executes **only on a run-owned QA schema** at MariaDB port 3309 and creates a private aggregate JSON report. Use `--population=research_opt_in` separately for participants opted in and not withdrawn. No public analytics route exists. A consistent read-only transaction prevents DB writes; no attempt settlement, no personal identifiers in aggregate.

Maturity is 24 h, stale grace 30 min for reporting only. Completion is distinct mature completed / mature starts; 0 denominator yields `null`. Attempt data updated after cutoff is **INCONCLUSIVE_AS_OF**, not guessed historical truth. Research withdrawal excludes the research population without altering lawful operational statistics. The report does not establish learning gains, academic QC or pilot GO.

## Owner acceptance / manual Git

Fase C exits after mandatory native Windows QA and guarded source `--verify`; Fase D is **manual Git by Owner**, with only explicitly approved paths and multi-paragraph `git commit -m "summary" -m "detail"`.
