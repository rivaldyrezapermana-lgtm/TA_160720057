# Design: Materials → Product BOM + Production Machines

**Date:** 2026-06-17
**Status:** Approved (design); pending implementation plan
**Project:** Labasa (Laravel 11, Blade, no API/TS layer)

## Goal

Refactor the materials relationship and add production machines:

1. Rename `production_materials` → `product_materials`.
2. Move the materials relationship from `Production` to `Product` (becomes a Bill of Materials / recipe).
3. Add `production_machine_id` to `productions` with a new `ProductionMachine` entity (full CRUD).
4. Remove the old `Production → ProductionMaterial` relationship and all code tied to it.

## Decisions (locked during brainstorming)

- **Semantics:** `product_materials` is a **Bill of Materials**. Column `qty_used` → `qty_required` (quantity of a material needed per 1 unit of the product).
- **Stock logic:** Production creation **no longer decrements `Material.stock`** and no longer picks materials. The product BOM is reference data only. This removes the manual per-batch material picker and the `Material::decrement` calls in `ProductionController@store`.
- **Data migration:** Backfill `product_materials` from existing `production_materials` joined to `productions`, taking the **latest** (`MAX(id)`) row per `(product_id, material_id)` pair to de-dupe collisions. Done in PHP for DB-driver portability. One-way (not reversible).
- **ProductionMachine scope:** Full model + CRUD. Fields: `name`, `code` (unique), `status` (active/maintenance/inactive), `capacity` (nullable int), `notes` (nullable). `productions.production_machine_id` is **nullable** (existing rows have no machine); `nullOnDelete`.

## Context notes

- Deliverables phrased as "API contracts", "DTOs", "frontend types/interfaces" map to this stack's real analogues: controller request/validation + response (DataTable JSON / redirects), and Blade views. There is no REST API, no DTO classes, and no TypeScript.
- The seeder currently creates **no `productions` and no `production_materials` rows**, so the data-migration backfill will usually find nothing in a freshly seeded DB. It exists to preserve any manually entered data.
- `ProductController` is real Eloquent code with a `syncSizes()` repeater pattern — BOM editing mirrors it via `syncMaterials()`.

## ERD change

```
BEFORE                                AFTER
──────                                ─────
products ──1:N── productions          products ──1:N── productions ──N:1── production_machines  (NEW)
                    │                     │
                    └─1:N─ production_     └─1:N─ product_materials  (renamed + re-pointed)
                          materials              (product_id, material_id, qty_required)
materials ──1:N── production_materials  materials ──1:N── product_materials
```

## Schema changes

### `product_materials` (was `production_materials`)
| column        | type                    | notes                          |
|---------------|-------------------------|--------------------------------|
| id            | bigint PK               |                                |
| product_id    | FK → products           | cascadeOnDelete                |
| material_id   | FK → materials          | cascadeOnDelete                |
| qty_required  | integer                 | per 1 unit of product          |
| timestamps    |                         |                                |
| unique(product_id, material_id) | | one BOM line per material      |

### `productions` (added column)
| column                | type                          | notes                |
|-----------------------|-------------------------------|----------------------|
| production_machine_id | nullable FK → production_machines | nullOnDelete     |

### `production_machines` (new)
| column     | type                                              | notes        |
|------------|--------------------------------------------------|--------------|
| id         | bigint PK                                         |              |
| name       | string                                           |              |
| code       | string unique                                    |              |
| status     | enum(active, maintenance, inactive) default active |            |
| capacity   | integer nullable                                 | units/day    |
| notes      | text nullable                                    |              |
| timestamps |                                                  |              |

## Migrations (3 new files, additive; existing `000008` untouched)

1. `create_production_machines_table`
2. `add_production_machine_id_to_productions_table` — nullable FK, `nullOnDelete`.
3. `create_product_materials_and_drop_production_materials`:
   - `up()`: create `product_materials`; backfill in PHP (load `production_materials` with each row's production `product_id`; dedupe to latest `id` per `product_id`+`material_id`; insert as `qty_required`); `dropIfExists('production_materials')`.
   - `down()`: recreate `production_materials` (empty — backfill is one-way); drop `product_materials`.

## Models

- **New** `ProductMaterial`: `$fillable = ['product_id','material_id','qty_required']`; `belongsTo` product + material.
- **New** `ProductionMachine`: `$fillable = ['name','code','status','capacity','notes']`; `STATUSES = ['active','maintenance','inactive']`; `hasMany(Production)`.
- **Delete** `ProductionMaterial`.
- `Product`: add `materials() { return $this->hasMany(ProductMaterial::class); }`.
- `Production`: remove `materials()`; add `machine() { return $this->belongsTo(ProductionMachine::class, 'production_machine_id'); }`; add `production_machine_id` to `$fillable`.
- `Material`: rename `productionMaterials()` → `productMaterials() { return $this->hasMany(ProductMaterial::class); }`.

## Backend logic

- **ProductController**: add `syncMaterials()` (mirrors `syncSizes`) called in `store`/`update`; add `materials` validation (`materials.*.material_id` exists:materials,id; `materials.*.qty_required` integer min:1); pass `$materials` (all materials) to `create`/`edit`; eager-load `materials.material` in `edit`/`show`.
- **ProductionController**: remove the material loop + `Material::decrement` from `store()`; remove `$materials` from `create()`; add `production_machine_id` (nullable, exists:production_machines,id) to store/update validation + writes; eager-load `machine` in `show`/`edit`; pass active machines to `create`/`edit`; remove `materials.material` eager loads.
- **New ProductionMachineController**: resource CRUD + `data()` DataTable JSON; `destroy` guarded if `productions()->exists()`; Indonesian flash messages.
- **MaterialController@destroy**: `productionMaterials()` → `productMaterials()` in the in-use guard.

## Routes (`routes/web.php`)

- `Route::resource('production-machines', ProductionMachineController::class);`
- `Route::get('production-machines', [ProductionMachineController::class, 'data'])->name('production-machines');` inside the `datatables.` group.

## Frontend (Blade)

- **New** `admin/production-machines/index.blade.php`, `create.blade.php`, `edit.blade.php`; add a sidebar link near Produksi.
- `admin/products/create.blade.php` + `edit.blade.php`: add a BOM materials repeater (checkbox + qty per material). `show.blade.php`: render BOM list.
- `admin/productions/create.blade.php`: remove "Bahan yang Digunakan" card; add "Mesin Produksi" select. `edit.blade.php`: add machine select. `show.blade.php`: remove "Bahan Digunakan" card; show machine name.

## Seeder

- Add 2–3 `production_machines`.
- Add a few `product_materials` BOM rows per product so the new relationship demos out of the box.

## Breaking changes

- `Production->materials` relationship removed — callers break (only the files listed above reference it).
- `ProductionMaterial` model deleted; `Material->productionMaterials()` renamed to `productMaterials()`.
- `production_materials` table dropped; `qty_used` → `qty_required`.
- Creating a production no longer decrements material stock (inventory behavior change).
- Data migration is one-way (`down()` cannot restore backfilled rows).

## Affected files

**Migrations (new):** 3 files above.
**Models:** `ProductMaterial` (new), `ProductionMachine` (new), `ProductionMaterial` (delete), `Product`, `Production`, `Material`.
**Controllers:** `ProductController`, `ProductionController`, `ProductionMachineController` (new), `MaterialController`.
**Routes:** `routes/web.php`.
**Views:** `admin/production-machines/*` (new), `admin/products/{create,edit,show}.blade.php`, `admin/productions/{create,edit,show}.blade.php`, `components/admin/sidebar.blade.php`.
**Seeder:** `DatabaseSeeder.php`.
**Docs:** `CLAUDE.md` (update the models/relationships + production stub notes).

## Testing

- `php artisan migrate:fresh --seed` succeeds; `product_materials` and `production_machines` populated.
- Product create/edit persists BOM; product show renders it.
- Production create/edit persists `production_machine_id`; no material stock change on creation.
- ProductionMachine CRUD + DataTable works; destroy blocked when productions reference it.
- Material destroy guard still blocks materials used in a BOM.
