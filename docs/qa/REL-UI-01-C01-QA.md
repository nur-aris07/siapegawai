# REL-UI-01 / Fase C / C-01 — Participant Workspace navigation + UX

**Scope approved:** REL-UI-01 B-REV01, baseline `e4fdd6f537e4d4be01b23f0269c816c102619989`.

## Classification

- **PRESERVED:** public/account/admin layouts; participant URL routes; CAT, Kelas, QQ, analytics computations, scores, history, entitlement, access policies, storage, logout POST + CSRF.
- **ADAPTED:** participant workspace header (five learning links), account/profile/logout grouping, dashboard learning tasks, reflection and public-exploration hierarchy, spacing.
- **NEW (UI behavior only):** keyboard- and pointer-accessible mobile menu, Escape/outside dismissal, local Vite entry. No backend endpoint or library.

## Functional and visual acceptance

1. `UI-NAV-01`: desktop 1024/1366/1440, one-line nav; five links (Ringkasan, Tryout saya, Kelas saya, Quiz Kilat, Analisis kemampuan) and active state.
2. `UI-NAV-02`: 320/375/768, toggle menu, all learning links, accessible profile and logout, no horizontal overflow.
3. `UI-ACC-03`: account menu Profile + POST/CSRF Logout; Escape restores focus, outside dismisses, Tab/Shift+Tab/focus visible.
4. `UI-PUB-04`: dashboard has Demo SKD, Bank soal contoh, Katalog kelas; public routes remain public.
5. `UI-FLOW-05`: CAT/Kelas/QQ detail URLs and hub remain unchanged, results/summary functional.
6. `UI-VIS-06/07`: metric charts remain as approved; no collisions/overlap at 320/375/768/1024/1366/1440 and 200% zoom, reduced motion.
7. `UI-SEC-08`: account/role/ownership and CSP-safe local JS no inline injection; backend untouched.
8. `UI-REG-09`: installer verify, `node --check`, focused PHPUnit, full suite, `npm run build`, visual UAT.
9. `UI-OWNER-10`: explicit OWNER-ACCEPTED required before Git. Owner alone executes Git commands.

**Local development QA only.** P0 release staging, independent restore, legal/ops and CSP rollout remain OPEN.

## Evidence status

- Installer preflight/dry-run/apply/verify: PENDING OWNER EXECUTION.
- Focused and full PHPUnit, Vite build: PENDING OWNER EXECUTION.
- Keyboard and browser UAT: PENDING OWNER EXECUTION.
- Production readiness: NOT CLAIMED.


## C-02 — Visual hierarchy, learning materials and navigation semantics

- Owner review after C-01: tall one-column class detail, markdown-like text read as unstructured plain prose, ambiguous back links, and too many identical visual treatments.
- Class detail is now a learning workspace with progress/practice/syllabus rail and a disclosure list of materials. The lesson bodies are hidden by default, remain HTML-accessible only to authorized enrolled participants, and use native keyboard-accessible `<details>`.
- Material/syllabus render CommonMark with `html_input=escape` and `allow_unsafe_links=false`. Raw HTML is not accepted as markup. Form POST/CSRF, route authorization, historical completion version and private media links are unchanged.
- The analysis hub no longer claims the participant arrived from Ringkasan; cross-domain links explicitly state their destination rather than assuming navigation history.
- Dashboard learning tasks now show local, labeled icons for Tryout, Kelas, QQ and Analytics; icons complement rather than replace text.
- Iconography comes from the existing local SVG sprite. Colors have status/action meaning and textual labels; no new image, JS dependency, migration, database operation, or scoring logic.
- Re-run focused class-learning UX + class progress/security regression + full PHPUnit and Vite build, then owner desktop/mobile and keyboard UAT. Do not mark PASS until evidence is provided.
- Release P0 CSP staging/restore/legal remain OPEN. No production readiness claim.


## C-03 — Owner-targeted class UI refinement and legacy regression repair

- C-02 automated evidence: installer 14/14, focused UX 5/30 PASS; full suite **190 PASS, 2 FAIL**, both from `SiapegawaiMaterialRevisionsTest` because existing rendered copy contract changed. These are NOT waived by visual acceptance.
- Keep existing design identity. On desktop, progress + practice + syllabus remain visually grouped in left rail; material list and reading contents remain the main right work area.
- DOM and mobile reading order now: progress, materials, practice, syllabus. **No duplicated markup, CSS-only order re-mapping or JS reorder**. At 320–768px material list is reachable immediately after progress, with logical Tab/screen-reader order.
- Reduce repeated uppercase labels, slightly compress progress panel on mobile, and change practice from dominant solid navy CTA to secondary outlined action. Contents stay unchanged, and material disclosure remains the primary task interaction.
- Material summaries use stable grid alignment for ordinal, long title, edition metadata, status and chevron. Native `<details name>` handles one-open-at-a-time semantics. `aria-controls` points to matching IDs without hardcoded `aria-expanded` that can become stale.
- Preserve old `EDISI v{n}` public text and `Progres selesai Anda tetap tersimpan` for revision/versioned-completion semantics. No legacy test change or scoring/progress mutations.
- Add focused regression for ordering, single DOM copy, long titles, CommonMark headings/lists, aria-controls targets.
- **Required** owner laptop: focused class UX + material revisions + progress and private media tests, then full PHPUnit and Vite build. Browser UAT at 320/375/390/430/768 and desktop 1280/1440/1920, keyboard/zoom, PDF and CTA.
- No release P0 closed and no production-readiness claim.
