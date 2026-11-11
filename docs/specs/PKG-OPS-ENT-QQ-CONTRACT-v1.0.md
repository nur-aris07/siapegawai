> **CATATAN HISTORIS (11 November 2026):** v1.0 ini digantikan untuk keputusan produk dan engineering oleh [`PKG-OPS-ENT-QQ-CONTRACT-v1.1.md`](./PKG-OPS-ENT-QQ-CONTRACT-v1.1.md) berdasarkan feedback final pemilik produk. Konten lama dipertahankan untuk penelusuran keputusan; butir yang bertentangan pada v1.0 tidak berlaku lagi.

# PKG-OPS-ENT / QQ — Entitlement and Shared Daily Quota Contract

**Document ID:** `PKG-OPS-ENT-QQ-01`  
**Date:** 10 November 2026  
**Status:** DRAFT interface contract for review. No package quota, migration or payment processing is authorized by this document.  
**Baseline:** `c66e6e35221f5e8fa3be7bb41527ed2aa1f06a92`.

## 1. Existing code and gap

From the inspected source:

- `app/Models/AccessGrant.php` and `database/migrations/2026_11_08_000002_create_siapegawai_registration_tables.php` already define `access_grants` with `user_id`, `grant_type`, optional `resource_type/resource_id`, `starts_at`, `ends_at`, `status`, `granted_by`, and `revoke_reason`.
- This table has **no field for QQ sessions per day**, no quota consumption ledger, no reset date and no atomic debit/idempotency contract. A generic grant record **does not** prove availability of QQ budget.
- `app/Models/TryoutPackage.php` models a CAT/tryout template, **not** an entitlement package. Do not conflate the two just because both use “package”.
- The project has no QQ model/route at the inspected baseline. `PKG-OPS-ENT` is a prerequisite to `QQ-CORE`.

## 2. Requirements already approved

- One **shared daily quota per participant** across all Quiz Kilat titles (not one quota per title).
- Access is governed by legitimate package/entitlement. Repeating a title is allowed while quota remains.
- Admin/superadmin workflow can manage packages and manually grant access; automatic checkout/payment gateway is out of scope.
- Account status/role and server authorization cannot be bypassed by hiding controls in Blade.
- The quota limit **must not be assumed to be five**; five was an example, not an accepted policy.

## 3. Proposed service API (not PHP code yet)

| Operation | Contract |
|---|---|
| `eligibility(user, now)` | Returns authorized/denied with policy ID and effective window; read-only; no credit inferred without grant. |
| `quotaSnapshot(user, now)` | Returns approved limit, consumed, available, local quota date, timezone, reset timestamp, provenance; same snapshot rendered in all QQ lists. |
| `startAttempt(user, quizEdition, idemKey, now)` | Single transaction: validate active account, current entitlement and title, lock daily quota, return existing attempt for same idempotency key or create attempt + **exactly one** consumption and first-item state. |
| `resumeAttempt(user, attemptId)` | Ownership/current state, no new debit; never uses client timer as authority. |
| `finishAttempt(user, attemptId)` | Atomic finalization; does not generate a second entitlement event. |
| `adjustQuota(actor, user, reference, reason)` | **Only after manual refund rules approved**; immutable audit and unique reference, no silent counter edit. |

Suggested result shape (names for discussion only): `allowed`, `reason_code`, `quota_date`, `timezone`, `limit`, `consumed`, `remaining`, `resets_at`, `entitlement_source`, `current_attempt_id`. Never return arbitrary admin notes or other users' grants to participants.

## 4. Atomicity and adversarial cases

**Transactional invariant:** for any participant and calendar quota date, `0 <= consumed <= approved_limit`. Multiple `start` calls for distinct titles race on the *same* ledger row. Database lock and unique idempotency/reference prevent double debit. A uniqueness constraint on `(user_id, quota_date)` by itself is insufficient unless every title shares the same counter/lock.

Recommended transaction outline:

1. Authenticate active participant and validate edition publication.
2. Resolve entitlement effective at **server time**. Reject if no valid policy.
3. Acquire row lock on member/day ledger (creating row safely under unique constraint if absent); prevent concurrent overdraw.
4. Look up idempotency key/user. If it already maps to an attempt, return that attempt with **no** additional consumption.
5. If budget available, insert attempt and usage event tied by unique attempt identity, increment consumed, persist first-item issue time, commit. On DB failure roll back everything.
6. On network failure after commit, `resume` or the repeated same idempotency request returns the single committed attempt. Never blindly perform a second start.

Prefer immutable consumption events for audit beside the aggregate counter and test invariant preservation with real MySQL/MariaDB transactions. The implementation must define lock ordering and avoid orphan event/attempt records. Do not use cache-only, PHP process memory, or client localStorage as authoritative quota storage.

## 5. Date boundaries and entitlement policy — DECISIONS REQUIRED

| ID | Policy question | Options / assessment to approve |
|---|---|---|
| ENT-01 | Daily limit | Per-package configured allowance; no numerical default approved. |
| ENT-02 | Reset timezone | `Asia/Jakarta` is a candidate only; UTC storage plus explicit timezone conversion, or another approved zone; show next reset. |
| ENT-03 | Free quota | None until allocated by a recognized explicit policy, or an approved free tier; no silent default. |
| ENT-04 | Multiple grants | Highest effective policy, additive, or prioritized grant; selection must be deterministic and documented. |
| ENT-05 | Debit point | Proposed one debit on first successful started attempt; retry/resume free. |
| ENT-06 | Abandoned/timeout session | Proposed still consumed; no automatic refund based solely on disconnect; admin incident handling needs policy. |
| ENT-07 | Grant expiry while actively answering | Proposed finish current valid attempt but deny new attempt; distinguish suspension/security revocation from ordinary expiry. |
| ENT-08 | Manual refund and authority | Superadmin with reason + audit and unique adjustment key, only under approved policy. |
| ENT-09 | Changing tier at midday | Decide whether same-day limit recalculates immediately or takes effect next quota bucket. |
| ENT-10 | Multi-tab & active sessions | Proposed max one active QQ attempt per member; choose resume-vs-refuse UX explicitly. |
| ENT-11 | Midnight boundary during attempt | Attempt consumes on its start bucket; running session continues under the chosen ENT-07 policy. |
| ENT-12 | Account suspended/revoked | Stop sensitive access immediately, retain historical usage and results. |

**Example (illustrative, NOT product setting):** if an entitlement permits `N` attempts/day and a participant has consumed `N`, starting *any* title must be denied until effective reset or legitimate adjustment. The same title is not privileged.

## 6. Server enforcement, privacy and support

- Query by `user_id` on every request; status and role cannot come from form input. Denial reasons may be `account_inactive`, `no_entitlement`, `expired_entitlement`, `quota_exhausted`, `existing_active_attempt`, `server_error` (example codes, not final public strings).
- Audit manual grant, revoke, quota adjustment and exceptional administrative recovery using the existing audit facility; never store payment secrets or misleading payment state.
- Keep the ledger and results after revocation for controlled history; whether an inactive account can view existing QQ review requires its own policy (default admin-scoped preservation, no participant access while inactive).
- Visible UI needs **real** remaining quota, effective reset and actionable explanation. No 5/day hardcode, fake “Unlimited”, purchase-now or invented price.
- Restrict support/operator capability; issuance/adjustment is distinct from read-only monitoring. No extra application role.

## 7. Acceptance tests (later implementation; not executed)

- [ ] Active user with explicit entitlement starts exactly once; quota remaining decreases by one.
- [ ] Same idempotency start request retried 10 times creates one attempt/debit.
- [ ] Distinct simultaneous starts for different titles with one unit remaining result in **one success only**.
- [ ] A resumed unfinished attempt consumes no additional quota.
- [ ] Entitlement expired/inactive/for another user rejects start and does not debit.
- [ ] Quota exhausted rejects all QQ titles, including repeats, without affecting CAT/Latihan Kelas.
- [ ] Reset boundary uses accepted timezone, even under delayed requests; two buckets are not merged.
- [ ] Changing entitlement mid-day behaves according to ENT-09.
- [ ] Grant expiry mid-session behaves according to ENT-07; suspension blocks immediately.
- [ ] Audit trails identify operator, affected user, reason, timestamp and idempotency reference, without secrets.
- [ ] Database deadlock retry does not duplicate usage, and transaction failure leaves no orphan attempt.
- [ ] Tests run against dedicated MySQL/MariaDB testing DB, not the application development DB.

## 8. Implementation gate

`PKG-OPS-ENT` is **not complete** merely because an `access_grants` record exists. `QQ-CORE` may begin only when ENT-01..ENT-12 are explicitly settled, the atomic ledger implementation passes real concurrency tests, and the UI/authorization acceptance is recorded. This contract is an aid to approval, **not a substitute for approval**.
