# FIG-P4 / V-03 — Network Fairness & Image Readiness UAT Template

**Scope:** timed figural QQ (first priority), CAT/Class media failures and mobile accessibility. **Status at delivery:** `BLOCKED / NOT YET MEASURED`. **No timer or quota policy changed in C-01.**

## Test setup

Use `siapegawai_testing` + existing FIG-P3 synthetic QQ 30, CAT 3 and Class 3; no real user sessions. Record device/browser, viewport 320/375/440 and desktop, CPU throttle, effective network/RTT, disabled cache, total image bytes and time of attempt. Do **not** record cookies/passwords, private image URLs or user identifiers. Observe QQ 5-second feedback separately.

### Read-only observer

1. In DevTools Network, select mobile and slow network profile **before** starting the timed question; retain a screenshot of the actual network waterfall with credentials hidden. Mark page navigation/HTML response end and the question issue/expiry from secure QA logs.
2. Run `tools/rel-verify/SGP-FIG-P4-QQ-READINESS-PROBE.js` as a DevTools Snippet while on the QQ timed question. It only samples already loaded DOM images; it does not fetch, retry, submit or reset timers. Its timer measurement begins **after the Snippet executes** and therefore cannot independently measure server-issue→first-image delay.
3. Cross-check all essential stem/option images visually and with `img.complete && img.naturalWidth > 0`. Confirm an answer may be selected, the remaining **server-clock** time, and response result. Capture first meaningful content and last required image response via Performance/Network timeline.
4. Repeat with 1 Mbps/300 ms RTT (example profile, **not SLA**), unstable/offline, missing image, multiple images/option, keyboard, 200% zoom, portrait/landscape, and normal baseline. No direct mutation of storage outside **disposable QA copy**.
5. Mark any question that times out **before all essential images are legible** a BLOCKER; do not silently extend QQ `seconds_per_question`, change answer lock, feedback 5s, quota or attempt issue semantics. A change to server timing requires separate Founder product decision **within Fase C**.

## Test matrix (to be filled using actual QA environment)

| Scenario | Network/device, media bytes | Issue→visible (ms) | Issue→all essential ready (ms) | Time remaining when ready | Actual result | Verdict |
|---|---|---:|---:|---:|---|---|
| Desktop fast / first view | — | — | — | — | — | BLOCKED |
| Mobile 375px throttled first view | — | — | — | — | — | BLOCKED |
| Mobile 320px + 200% zoom | — | — | — | — | — | BLOCKED |
| Offline before image response | — | — | — | — | — | BLOCKED |
| Interrupted image + refresh/reconnect | — | — | — | — | — | BLOCKED |
| High-media-density stem/options | — | — | — | — | — | BLOCKED |
| QQ feedback + review after answer lock | — | — | — | — | — | BLOCKED |
| CAT/Class slow image + no unexpected navigation | — | — | — | — | — | BLOCKED |

## Final business decision

- Observed unfair timer / missing-image case: ______.
- Approved target network/device/cohort (Founder): ______.
- Remediation (if needed): authoring optimization / explicit ready handshake design / accessibility rule / cohort gate / other: ______.
- Product approval for **any clock semantics change**: `NOT GRANTED` unless separate signed decision; details: ______.
- Tech/QA and Founder review names: ______; date: ______.
- Final `V-03`: `PASS / FAIL / BLOCKED / OWNER-WAIVED` (default BLOCKED). A waiver cannot conceal inaccessible essential images or security leakage.

**Reminder:** PILOT QQ, PUBLIC RELEASE and SALES remain OFF. P4 feature acceptance does not itself grant pilot GO.
