# PKG-CATALOG-01 / Fase C / C-04 — Blade ordering view fix

**Status:** QA owner pending. Baseline Git HEAD `4dce1264d613177576867d37eaec0104ba143365`. C-01, C-02, C-03 already applied. No schema or runtime business rules changed.

## Failure and proven root cause

Owner focused suite C-03 reported **10 passed / 1 failed** (105 assertions). `GET /admin/paket/urutan` raised HTTP 500: `ParseError unexpected token endforeach, expecting elseif or else or endif` in Blade view `resources/views/admin/packages/ordering.blade.php`.

The markup had an inline conditional with `Diarsipkan@endif` adjacent to a word; Blade directive detection can leave the @if unclosed. C-04 replaces the inline conditional with escaped Blade interpolation:

```blade
<small>{{ $plan->code }}{{ $plan->is_archived ? ' · Diarsipkan' : '' }}</small>
```

This retains the same visible label without conditional directives. Existing focused `test_ordering_is_separate_from_creation_and_new_packages_append_to_last_position` calls the actual ordering route and catches Blade render errors. The other ten focused tests already passed before this patch.

## Verification gates

1. `php siapegawai-update-PKG-CATALOG-01-C04-REVIEW.php --dry-run && php siapegawai-update-PKG-CATALOG-01-C04-REVIEW.php --apply && php siapegawai-update-PKG-CATALOG-01-C04-REVIEW.php --verify`.
2. `php artisan optimize:clear && php artisan view:cache && php artisan view:clear` — compile views and flush cache; `view:cache` alone is **not** a substitute for executing the route.
3. `php artisan test tests\Feature\SiapegawaiPackageCatalogTest.php && php artisan test tests\Feature\SiapegawaiPackageThrottleTest.php && php artisan test && git diff --check && git status --short --branch` on isolated test DB. Vite was previously built PASS and no CSS/JS was changed by C-04.
4. UAT actual route `/admin/paket/urutan` as active superadmin; archived label, drag handle on desktop, Naik/Turun on mobile, cancel, save confirmation, stale tab refusal; verify modal/mobile issues from C-03.

No migration, SQL, .env, Composer, or Git writes by installer. No acceptance claimed before owner QA and UAT.
