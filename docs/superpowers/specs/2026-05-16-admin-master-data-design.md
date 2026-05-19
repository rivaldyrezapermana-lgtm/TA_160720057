# Admin Master Data — Eloquent Wiring — Design

**Date:** 2026-05-16
**Scope:** The 5 admin master-data resources — categories, products, materials, suppliers, users.

## Goal

Replace the stub controllers (hardcoded `(object)[…]` data, no-op store/update/destroy) with real Eloquent-backed CRUD. Models, migrations, routes, and views already exist.

## Decisions

- **Product images:** handled — uploaded to `storage/app/public/products`, path saved to `products.image`; `storage:link` created; upload also added to the edit form.
- **Delete safety:** a record other data depends on cannot be deleted — `destroy()` returns an Indonesian `error` flash instead.
- **Validation messages:** Indonesian — app locale set to `id`, `lang/id/validation.php` added.

## Controllers

All 5 use route-model binding and inline `$request->validate()`. `show()` redirects to `edit()` (already the case).

| Resource | Notes |
| --- | --- |
| Category | `index` uses `withCount('products')`; slug auto-derived from name when blank; delete blocked when it has products |
| Product | `data()` is the DataTables JSON feed; `store`/`update` handle image upload + S/M/L/XL size variants (`updateOrCreate` keyed on size); delete blocked when referenced by orders/productions/sales history |
| Material | `data()` JSON feed; `status` from `isLowStock()`; delete blocked when used in purchases/production |
| Supplier | server-rendered index; delete blocked when it has purchases |
| User | password required on create, optional on update (kept when blank), auto-hashed by model cast; cannot delete own account or a user with orders/productions |

## View changes

- `admin/products/show.blade.php` — fixed to read the real model (`category?->name`, `chest_cm`/`length_cm`/`sleeve_cm`, sizes as models); shows the image.
- `admin/products/edit.blade.php` — added image upload + editable size variants.
- `layouts/admin.blade.php` — added a red `session('error')` flash block for delete-guard messages.

## Validation

- App locale → `id`; `lang/en/*` published for fallback; `lang/id/validation.php` added with Indonesian rule messages + an `attributes` map.

## Testing

- `phpunit.xml` — SQLite in-memory enabled.
- `tests/Feature/Admin/MasterDataTest.php` — each resource's index/store/update/destroy plus delete-guard and password-hashing cases.
- `vendor/bin/pint` on changed files; `php artisan migrate:fresh --seed` to confirm against MySQL.
