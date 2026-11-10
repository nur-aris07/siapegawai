# REL — Visual / Browser UAT Register (Open Items, 10 Nov 2026)

**Status:** OPEN register, not visual sign-off.  
**Baseline:** `c66e6e35221f5e8fa3be7bb41527ed2aa1f06a92`.  
**Reference:** `docs/design/DESIGN-SYSTEM-AND-VISUAL-CONTRACT.md`, `docs/design/VISUAL-ACCEPTANCE-CHECKLIST.md`, `docs/design/ADMIN-WORKSPACE-REFERENCE.md`.

## Evidence currently available

- `S7.4 CLS-MON`: PHPunit **105 PASS / 837 assertions** following Yajra/visual hotfix, Vite PASS; subsequently covered by later full regressions. **No owner-signoff screenshot for final hotfix** in this register.
- `S7.5 CLS-MEDIA-01`: **112 PASS / 897 assertions**, migration `000012` DONE, Vite PASS; protected PDF, chapter and video cases covered by automated tests.
- `S7.5 CLS-MEDIA-02`: **121 PASS / 994 assertions**, migration `000013` DONE, Vite PASS; version snapshots and completion histories covered by tests.
- **Manual browser/visual:** none of these terminal successes proves 320–1440px responsive spacing, CSP console cleanliness, PDF actual download experience, 3rd-party video behavior or keyboard accessibility. Treat as **NOT VERIFIED**, not failed.

## Targeted UAT matrix

| Work item | Page / condition | Manual acceptance evidence to capture | Current state |
|---|---|---|---|
| REL-VIS-01 | `/admin/kelas/{class}/monitoring` desktop 1440/1366 | Metric hierarchy/spacing vs contract, DataTables search as-you-type and status filter **without full reload**, real count/data | OPEN |
| REL-VIS-02 | Monitoring detail participant revoked | Historic progress and practice results visible to authorized admin; other class ID denied | OPEN |
| REL-VIS-03 | Admin edit class 1024/768/375/320 | Distinct media/revision sections have real spacing, no panels touching or horizontal viewport overflow | OPEN |
| REL-VIS-04 | Member class material, draft revision staged | Participant still sees published v1 prior to v2 publication; no content leakage | OPEN |
| REL-VIS-05 | Member class material after v2 publish | Completion still 1 record and old version info appears; PDF/video links unchanged | OPEN |
| REL-VIS-06 | Protected PDF direct URL then revoke/regrant | Active enrollee download works, revoked URL denies, restored enrollee works; no `public/storage` exposure | OPEN |
| REL-VIS-07 | Mobile 320/375 and zoom 200% | Typography, focus order, toolbar/table overflow cue, tap targets, headings, empty and error states | OPEN |
| REL-VIS-08 | JS console, CSP report, network failed request | No injection/inline violation, meaningful permanent retry/error state, JS failure does not falsely report success | OPEN |
| REL-VIS-09 | CAT and class practice smoke | Prior attempts/results preserved; regression shell unchanged; no sample data altered during review | OPEN |

## Evidence form (copy for each route)

```text
Work item: REL-VIS-__
Date/time and browser:
Role/user type (never include secret/PII):
Source SHA:
Route + class/material fixture (non-sensitive):
Viewport width x height / zoom:
Screenshot before/source reference:
Screenshot after:
Browser console/CSP findings:
Expected versus observed:
PASS / FAIL / BLOCKED:
Reviewer / approval:
Git issue/fix SHA if any:
```

## Gate

Do not label `VISUAL ACCEPTED` or `PRODUCTION READY` until all applicable items have evidence and reviewer agreement. This register **does not block publishing documentation-only QQ-SPEC**. It does block making unsupported statements that S7.4/S7.5 browser/visual acceptance was completed. For any discovered P0 defect, create a separate scoped hotfix with its own installer, tests, and Git handover.
