# Auto-SKU & Production Stage Boundaries — Design

**Date:** 2026-07-15
**Status:** Approved by user (conversation), pending implementation.

## Goal

Three revisions to the admin product/production modules:

1. Product SKU is generated automatically (sequential `SKU-nnnn`), never typed by the admin.
2. Strict quantity boundaries on production stages so a batch can never process more than
   its target and a stage can never receive more than its predecessor produced.
3. Stages remain editable after being marked **Selesai**, until the whole batch completes.

## 1. Auto-generated SKU

- `Product::nextSku(): string` — static helper. Scans existing `sku` values matching
  `SKU-<digits>`, takes the highest number, returns `SKU-` + (max+1) zero-padded to 4
  digits (min width; numbers past 9999 just grow wider). Seeder ends at `SKU-0004`,
  so the first admin-created product is `SKU-0005`. The existing unique DB constraint
  on `products.sku` remains the concurrency safety net.
- `ProductController::store()` — stops validating/reading `sku` from the request;
  assigns `Product::nextSku()` server-side.
- `ProductController::update()` — ignores any submitted `sku`; the stored SKU is
  immutable. The shared `rules()` drops the `sku` rule entirely.
- `products/create.blade.php` — SKU input removed; replaced with a read-only display
  "Dibuat otomatis saat disimpan".
- `products/edit.blade.php` — SKU shown read-only (same pattern as the locked stock
  field), no `name` attribute so nothing is submitted.

## 2. Stage quantity boundaries (strict)

Enforced in `ProductionController::updateStage()`, Indonesian validation/error messages:

- `input_qty` ≤ `production.planned_qty` — for every stage, including the first.
  The batch can never exceed the target, so final `actual_qty` cannot overshoot it.
- Non-first stages: `input_qty` ≤ previous stage's **current** `output_qty`.
- Existing rule kept: `output_qty` ≤ `input_qty` (same stage).
- New (needed by §3): `output_qty` ≥ next stage's **current** `input_qty` — a stage's
  output cannot be edited below what the next stage has already taken in.

Resulting invariant chain per batch:
`stage.output ≤ stage.input ≤ prev.output ≤ … ≤ planned_qty`, and
`stage.output ≥ next.input`.

UI: on `productions/show.blade.php`, each stage's Input field gets `max="…"`
(min(planned, previous output)) and a helper text showing the allowed maximum.

## 3. Completed stages stay editable

- A stage with status `completed` still renders its Input/Output/Mesin fields with a
  **Simpan** button (action `save`) so quantities can be corrected after Selesai.
- Batch-level lock unchanged: once `production.status === 'completed'` (packing
  finished → product stock credited, materials consumed once), all stages are
  read-only. Editing past that point would desync already-moved stock.

## Testing (TDD, PHPUnit + RefreshDatabase, no factories)

- New `tests/Feature/Admin/ProductSkuTest.php`:
  - store without `sku` → product saved with next sequential SKU;
  - two consecutive stores → distinct sequential SKUs;
  - update with a forged `sku` → stored SKU unchanged.
- Extend `tests/Feature/Admin/ProductionFlowTest.php`:
  - input over `planned_qty` rejected;
  - input over previous stage's output rejected;
  - output below next stage's input rejected;
  - completed (non-batch-final) stage can still be saved with corrected quantities.

## Non-goals

- No SKU format configurability, no per-category prefixes.
- No reopening a completed batch / reversing stock movements.
- No changes to the 50% gate logic itself.

## Decisions & trade-offs

- SKU generation lives in an explicit `nextSku()` call from the controller, not a
  model `creating` event — seeder and tests create products with explicit SKUs and
  would need bypass logic under an event.
- Boundary checks read the already-loaded `stages` relation (same pattern as
  `stageUnlocked()`), no extra queries.
