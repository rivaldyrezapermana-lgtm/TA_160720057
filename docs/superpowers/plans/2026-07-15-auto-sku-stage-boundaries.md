# Auto-SKU & Production Stage Boundaries Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Auto-generate sequential product SKUs, enforce strict production-stage quantity boundaries (never over target, never over the previous stage's output), and keep completed stages editable until the batch completes.

**Architecture:** Laravel 11 MVC. `Product::nextSku()` generates SKUs server-side; `store()`/`update()` stop reading `sku` from requests. Two new helpers on `Production` (`stageMaxInput`, `stageMinOutput`) feed both the `updateStage` validation and the show-page UI. A batch-completed guard in `updateStage` makes the existing view-level lock authoritative on the backend.

**Tech Stack:** PHP 8, Laravel 11, MySQL (dev) / SQLite in-memory (tests via `phpunit.xml`), Blade + Tailwind CDN + jQuery. PHPUnit with `RefreshDatabase`; models created via `Model::create` (only `UserFactory` exists — do not add factories).

**Spec:** `docs/superpowers/specs/2026-07-15-sku-stage-boundaries-design.md`

## Global Constraints

- User-facing strings are **Bahasa Indonesia** — match existing copy (flash: `->with('success'|'error', '...')`, validation messages in Indonesian).
- PowerShell shell: chain commands with `;` not `&&`. Never run `git commit`/`git push` — the user handles all git operations. Wherever a task says "Commit", **stage nothing and instead stop and report the task is ready for the user to commit.**
- Stage list is fixed: `ProductionStage::STAGES = ['design','sample','cutting','sewing','qc','packing']`.
- Run a single test class with `php artisan test --filter=ClassName`; full suite with `php artisan test`.
- In tests create an admin via `User::create([..., 'role' => User::ROLE_ADMIN])` and `actingAs()` (see `tests/Feature/Admin/MasterDataTest.php`).

---

## File Structure

**New files:**
- `tests/Feature/Admin/ProductSkuTest.php`

**Modified files:**
- `app/Models/Product.php` — add static `nextSku()`.
- `app/Models/Production.php` — add `stageMaxInput()`, `stageMinOutput()`.
- `app/Http/Controllers/Admin/ProductController.php` — generate SKU on store, ignore on update, drop `sku` rule.
- `app/Http/Controllers/Admin/ProductionController.php` — boundary validation + completed-batch guard in `updateStage()`.
- `resources/views/admin/products/create.blade.php` — SKU input → "dibuat otomatis" display.
- `resources/views/admin/products/edit.blade.php` — SKU input → read-only display.
- `resources/views/admin/productions/show.blade.php` — input `max`/output `min` hints + Simpan button on completed stages.
- `tests/Feature/Admin/MasterDataTest.php` — update 2 SKU-dependent tests.
- `tests/Unit/ProductionStageFlowTest.php` — extend with helper tests.
- `tests/Feature/Admin/ProductionFlowTest.php` — extend with boundary tests.

---

## Task 1: Auto-generated SKU — model helper, controller, forms

**Files:**
- Modify: `app/Models/Product.php`
- Modify: `app/Http/Controllers/Admin/ProductController.php`
- Modify: `resources/views/admin/products/create.blade.php:14`
- Modify: `resources/views/admin/products/edit.blade.php:15`
- Modify: `tests/Feature/Admin/MasterDataTest.php:93-128`
- Test: `tests/Feature/Admin/ProductSkuTest.php`

**Interfaces:**
- Produces: `Product::nextSku(): string` — returns `SKU-` + (highest existing `SKU-<digits>` number + 1), zero-padded to 4 digits. `ProductController::store()` assigns it; `update()` never touches `sku`; `rules()` has no `sku` entry.

- [x] **Step 1: Write the failing test**

Create `tests/Feature/Admin/ProductSkuTest.php`:
```php
<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSkuTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Uji', 'email' => 'admin.uji@labasa.test',
            'password' => 'password', 'role' => User::ROLE_ADMIN,
        ]);
    }

    /** Minimal valid product payload — note: no 'sku' key. */
    private function payload(Category $cat, array $extra = []): array
    {
        return array_merge([
            'name' => 'Gamis Baru', 'category_id' => $cat->id, 'price' => 150000, 'is_active' => '1',
        ], $extra);
    }

    public function test_store_generates_first_sku(): void
    {
        $cat = Category::create(['name' => 'Gamis', 'slug' => 'gamis']);

        $this->actingAs($this->admin())
            ->post(route('admin.products.store'), $this->payload($cat))
            ->assertRedirectToRoute('admin.products.index');

        $this->assertDatabaseHas('products', ['name' => 'Gamis Baru', 'sku' => 'SKU-0001']);
    }

    public function test_store_continues_from_highest_existing_sku(): void
    {
        $cat = Category::create(['name' => 'Gamis', 'slug' => 'gamis']);
        Product::create(['category_id' => $cat->id, 'name' => 'Lama', 'sku' => 'SKU-0007', 'price' => 1000, 'stock' => 0]);
        // Non-matching SKU formats are ignored by the generator.
        Product::create(['category_id' => $cat->id, 'name' => 'Custom', 'sku' => 'GAM-999', 'price' => 1000, 'stock' => 0]);

        $this->actingAs($this->admin())
            ->post(route('admin.products.store'), $this->payload($cat));

        $this->assertDatabaseHas('products', ['name' => 'Gamis Baru', 'sku' => 'SKU-0008']);
    }

    public function test_consecutive_stores_get_distinct_skus(): void
    {
        $cat = Category::create(['name' => 'Gamis', 'slug' => 'gamis']);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.products.store'), $this->payload($cat, ['name' => 'Produk A']));
        $this->actingAs($admin)->post(route('admin.products.store'), $this->payload($cat, ['name' => 'Produk B']));

        $this->assertDatabaseHas('products', ['name' => 'Produk A', 'sku' => 'SKU-0001']);
        $this->assertDatabaseHas('products', ['name' => 'Produk B', 'sku' => 'SKU-0002']);
    }

    public function test_update_cannot_change_sku(): void
    {
        $cat = Category::create(['name' => 'Gamis', 'slug' => 'gamis']);
        $product = Product::create(['category_id' => $cat->id, 'name' => 'Ada', 'sku' => 'SKU-0001', 'price' => 1000, 'stock' => 0]);

        $this->actingAs($this->admin())
            ->put(route('admin.products.update', $product), [
                'name' => 'Ada', 'category_id' => $cat->id, 'price' => 2000, 'sku' => 'HACK-99',
            ])
            ->assertRedirect();

        $this->assertEquals('SKU-0001', $product->fresh()->sku);
    }
}
```

- [x] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ProductSkuTest`
Expected: FAIL — store without `sku` hits the `required` rule (`test_store_generates_first_sku` gets a redirect back with errors, no product row).

- [x] **Step 3: Add `Product::nextSku()`**

In `app/Models/Product.php`, add below `computeHpp()`:
```php
    /** Next sequential SKU: highest existing SKU-<digits> + 1, zero-padded to 4. */
    public static function nextSku(): string
    {
        $max = static::where('sku', 'like', 'SKU-%')
            ->pluck('sku')
            ->map(fn (string $sku) => preg_match('/^SKU-(\d+)$/', $sku, $m) ? (int) $m[1] : 0)
            ->max() ?? 0;

        return 'SKU-'.str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }
```

- [x] **Step 4: Stop reading `sku` from requests in the controller**

In `app/Http/Controllers/Admin/ProductController.php`:

In `store()` (line 51), replace `'sku' => $data['sku'],` with:
```php
            'sku' => Product::nextSku(),
```

In `update()` (line 93), delete the line `'sku' => $data['sku'],` entirely — the `fill()` block becomes:
```php
        $product->fill([
            'category_id' => $data['category_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'is_active' => $request->boolean('is_active'),
        ]);
```

In `rules()` (line 140), delete the `'sku' => [...]` line. Then remove the now-unused `?Product $product = null` parameter and its `use Illuminate\Validation\Rule;`-based ignore — the signature becomes `private function rules(): array` and both call sites become `$this->rules()`. (`Rule` is still used for `category_id`'s `Rule::exists`, keep the import.)

- [x] **Step 5: Update the two SKU-dependent MasterDataTest tests**

In `tests/Feature/Admin/MasterDataTest.php`:

1. `test_product_can_be_created_with_sizes` (line 93): remove the `'sku' => 'GAM-001',` line from the POST payload, and change the assertion
   from `$this->assertDatabaseHas('products', ['sku' => 'GAM-001', 'is_active' => true]);`
   to:
```php
        $this->assertDatabaseHas('products', ['name' => 'Gamis Navy', 'sku' => 'SKU-0001', 'is_active' => true]);
```
   (`RefreshDatabase` guarantees an empty table, so the generated SKU is `SKU-0001`.)

2. `test_product_requires_a_unique_sku` (line 115): delete the whole method — SKU is no longer user input; uniqueness is guaranteed by the generator plus the existing DB unique constraint, and generation is covered by `ProductSkuTest`.

- [x] **Step 6: Replace the SKU inputs in the product forms**

In `resources/views/admin/products/create.blade.php`, replace line 14:
```blade
            <x-ui.input name="sku" label="SKU" required />
```
with:
```blade
            <div class="field">
                <label class="label">SKU</label>
                <input type="text" value="Dibuat otomatis saat disimpan" class="input bg-ink-50" readonly disabled>
            </div>
```

In `resources/views/admin/products/edit.blade.php`, replace line 15:
```blade
            <x-ui.input name="sku" label="SKU" :value="$product->sku" required />
```
with:
```blade
            <div class="field">
                <label class="label">SKU</label>
                <input type="text" value="{{ $product->sku }}" class="input bg-ink-50" readonly disabled>
                <p class="field-help">SKU dibuat otomatis dan tidak bisa diubah.</p>
            </div>
```
(No `name` attribute → nothing is submitted; same pattern as the locked stock field below it.)

- [x] **Step 7: Run tests to verify they pass**

Run: `php artisan test --filter=ProductSkuTest`
Expected: PASS (4 tests).

Run: `php artisan test --filter=MasterDataTest`
Expected: PASS (one test fewer than before — the deleted uniqueness test).

- [x] **Step 8: Commit** — stop and report Task 1 ready for the user to commit (files: `Product.php`, `ProductController.php`, both product form views, `ProductSkuTest.php`, `MasterDataTest.php`).

---

## Task 2: Stage boundary helpers on the Production model

**Files:**
- Modify: `app/Models/Production.php`
- Test: `tests/Unit/ProductionStageFlowTest.php` (extend)

**Interfaces:**
- Consumes: `Production::stages` loaded relation, `ProductionStage::STAGES` order (same pattern as existing `stageUnlocked()`).
- Produces:
  - `Production::stageMaxInput(ProductionStage $stage): int` — upper bound for the stage's input: `planned_qty` for the first stage, otherwise `min(planned_qty, previous stage's output_qty)`.
  - `Production::stageMinOutput(ProductionStage $stage): int` — lower bound for the stage's output: `0` for the last stage, otherwise the next stage's current `input_qty`.
  - Both read the **loaded** `stages` relation — callers must have `stages` loaded.

- [x] **Step 1: Write the failing tests**

Add to `tests/Unit/ProductionStageFlowTest.php` (the `batch()` helper already exists in this file):
```php
    public function test_stage_max_input_is_capped_by_planned_and_previous_output(): void
    {
        $prod = $this->batch(100);
        $design = $prod->stages->firstWhere('stage', 'design');
        $this->assertSame(100, $prod->stageMaxInput($design)); // first stage: planned qty

        $prod->stages()->where('stage', 'design')->update(['output_qty' => 60]);
        $prod = $prod->fresh('stages');
        $sample = $prod->stages->firstWhere('stage', 'sample');
        $this->assertSame(60, $prod->stageMaxInput($sample)); // min(100, design output 60)
    }

    public function test_stage_min_output_follows_next_stage_input(): void
    {
        $prod = $this->batch(100);
        $prod->stages()->where('stage', 'sample')->update(['input_qty' => 50]);
        $prod = $prod->fresh('stages');

        $design = $prod->stages->firstWhere('stage', 'design');
        $packing = $prod->stages->firstWhere('stage', 'packing');

        $this->assertSame(50, $prod->stageMinOutput($design)); // sample already took 50 in
        $this->assertSame(0, $prod->stageMinOutput($packing)); // last stage: no floor
    }
```

- [x] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ProductionStageFlowTest`
Expected: FAIL — `Call to undefined method App\Models\Production::stageMaxInput()`.

- [x] **Step 3: Implement the helpers**

In `app/Models/Production.php`, add below `stageUnlocked()`:
```php
    /** Upper bound for a stage's input: the batch target, capped by the previous stage's output. */
    public function stageMaxInput(ProductionStage $stage): int
    {
        $order = ProductionStage::STAGES;
        $idx = array_search($stage->stage, $order, true);
        $planned = (int) $this->planned_qty;

        if ($idx === false || $idx === 0) {
            return $planned;
        }

        $prev = $this->stages->firstWhere('stage', $order[$idx - 1]);

        return min($planned, (int) ($prev?->output_qty ?? 0));
    }

    /** Lower bound for a stage's output: what the next stage has already taken in. */
    public function stageMinOutput(ProductionStage $stage): int
    {
        $order = ProductionStage::STAGES;
        $idx = array_search($stage->stage, $order, true);

        if ($idx === false || $idx === count($order) - 1) {
            return 0;
        }

        $next = $this->stages->firstWhere('stage', $order[$idx + 1]);

        return (int) ($next?->input_qty ?? 0);
    }
```

- [x] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ProductionStageFlowTest`
Expected: PASS (all tests in the class, including the pre-existing ones).

- [x] **Step 5: Commit** — stop and report Task 2 ready for the user to commit (files: `Production.php`, `ProductionStageFlowTest.php`).

---

## Task 3: Enforce boundaries + batch lock in `updateStage`

**Files:**
- Modify: `app/Http/Controllers/Admin/ProductionController.php:179-244`
- Test: `tests/Feature/Admin/ProductionFlowTest.php` (extend)

**Interfaces:**
- Consumes: `Production::stageMaxInput/stageMinOutput` (Task 2), existing `stageUnlocked()`, existing route `admin.productions.stage` (PATCH).
- Produces: `updateStage()` rejects `input_qty > stageMaxInput`, `output_qty < stageMinOutput` (validation errors, Indonesian messages), and rejects **any** stage edit once `production.status === 'completed'` (flash `error`). Completed stages remain editable via `action=save` before that.

- [x] **Step 1: Write the failing tests**

Add to `tests/Feature/Admin/ProductionFlowTest.php` (the `admin()` and `batch()` helpers already exist in this file; `batch()` returns `[$prod, $product, $kain]` with `planned_qty = 100` and design `input_qty = 100`):
```php
    public function test_input_cannot_exceed_planned_target(): void
    {
        [$prod] = $this->batch();
        $design = $prod->stages->firstWhere('stage', 'design');

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $design]), [
                'action' => 'save', 'input_qty' => 150, 'output_qty' => 0,
            ])
            ->assertSessionHasErrors('input_qty');

        $this->assertEquals(100, $design->fresh()->input_qty); // unchanged
    }

    public function test_input_cannot_exceed_previous_stage_output(): void
    {
        [$prod] = $this->batch();
        $prod->stages()->where('stage', 'design')->update(['output_qty' => 60, 'status' => 'in_progress']);
        $sample = $prod->stages()->where('stage', 'sample')->first();

        // 70 > design's output of 60 → rejected.
        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $sample]), [
                'action' => 'save', 'input_qty' => 70, 'output_qty' => 0,
            ])
            ->assertSessionHasErrors('input_qty');

        // Exactly at the limit → accepted.
        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $sample]), [
                'action' => 'save', 'input_qty' => 60, 'output_qty' => 0,
            ])
            ->assertSessionHas('success');

        $this->assertEquals(60, $sample->fresh()->input_qty);
    }

    public function test_output_cannot_drop_below_next_stage_input(): void
    {
        [$prod] = $this->batch();
        $prod->stages()->where('stage', 'design')->update(['output_qty' => 60, 'status' => 'in_progress']);
        $prod->stages()->where('stage', 'sample')->update(['input_qty' => 50, 'status' => 'in_progress']);
        $design = $prod->stages()->where('stage', 'design')->first();

        // Sample already took in 50 → design's output can't be edited down to 40.
        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $design]), [
                'action' => 'save', 'input_qty' => 100, 'output_qty' => 40,
            ])
            ->assertSessionHasErrors('output_qty');

        $this->assertEquals(60, $design->fresh()->output_qty); // unchanged
    }

    public function test_completed_stage_can_still_be_edited_until_batch_completes(): void
    {
        [$prod] = $this->batch();
        $prod->stages()->where('stage', 'design')->update(['input_qty' => 100, 'output_qty' => 90, 'status' => 'completed']);
        $design = $prod->stages()->where('stage', 'design')->first();

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $design]), [
                'action' => 'save', 'input_qty' => 100, 'output_qty' => 85,
            ])
            ->assertSessionHas('success');

        $this->assertEquals(85, $design->fresh()->output_qty);
        $this->assertEquals('completed', $design->fresh()->status); // save doesn't reopen it
    }

    public function test_stages_locked_after_batch_completed(): void
    {
        [$prod] = $this->batch();
        $prod->stages()->update(['output_qty' => 100, 'input_qty' => 100, 'status' => 'in_progress']);
        $packing = $prod->stages()->where('stage', 'packing')->first();

        $this->actingAs($this->admin())->patch(route('admin.productions.stage', [$prod, $packing]), [
            'action' => 'finish', 'input_qty' => 100, 'output_qty' => 100,
        ]);
        $this->assertEquals('completed', $prod->fresh()->status);

        $design = $prod->stages()->where('stage', 'design')->first();
        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $design]), [
                'action' => 'save', 'input_qty' => 100, 'output_qty' => 50,
            ])
            ->assertSessionHas('error');

        $this->assertEquals(100, $design->fresh()->output_qty); // unchanged
    }
```

- [x] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ProductionFlowTest`
Expected: FAIL — the four new boundary/lock tests fail (no caps, no batch guard); `test_completed_stage_can_still_be_edited_until_batch_completes` may already pass (backend never blocked it), that is fine.

- [x] **Step 3: Implement guard + boundary validation**

In `app/Http/Controllers/Admin/ProductionController.php` `updateStage()`, insert a batch-completed guard right after the `$stage = $production->stages->firstWhere('id', $stage->id);` line (line 183):
```php
        if ($production->status === 'completed') {
            return back()->with('error', 'Batch sudah selesai. Tahapan tidak bisa diubah lagi.');
        }
```

Then replace the `$request->validate([...])` block (lines 187–193) with:
```php
        $maxInput = $production->stageMaxInput($stage);
        $minOutput = $production->stageMinOutput($stage);

        $data = $request->validate([
            'input_qty' => ['nullable', 'integer', 'min:0', 'max:'.$maxInput],
            'output_qty' => ['nullable', 'integer', 'min:'.$minOutput, 'lte:input_qty'],
            'production_machine_id' => ['nullable', 'exists:production_machines,id'],
        ], [
            'input_qty.max' => 'Input maksimal '.$maxInput.' pcs (dibatasi target batch dan output tahap sebelumnya).',
            'output_qty.min' => 'Output minimal '.$minOutput.' pcs karena tahap berikutnya sudah menerima sebanyak itu.',
            'output_qty.lte' => 'Output tidak boleh melebihi input.',
        ]);
```
Everything else in the method (machine-category check, gate check, transaction, completion) stays unchanged.

- [x] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ProductionFlowTest`
Expected: PASS (8 tests: 3 pre-existing + 5 new). Note the pre-existing idempotency test now exits via the new guard on its second `finish` — its assertions (no double stock credit, single `production_materials` row) still hold.

- [x] **Step 5: Run the full suite for regressions**

Run: `php artisan test`
Expected: PASS (all).

- [x] **Step 6: Commit** — stop and report Task 3 ready for the user to commit (files: `ProductionController.php`, `ProductionFlowTest.php`).

---

## Task 4: Show-page UI — boundary hints + editable completed stages

**Files:**
- Modify: `resources/views/admin/productions/show.blade.php:50-86`

**Interfaces:**
- Consumes: `Production::stageMaxInput/stageMinOutput` (Task 2); `$production->stages` is already eager-loaded by `show()`.

- [x] **Step 1: Add per-stage bounds to the `@php` block**

In `resources/views/admin/productions/show.blade.php`, inside the stage `@foreach`, extend the `@php` block (lines 31–40) with two lines after `$machines = ...`:
```php
                        $maxIn = $production->stageMaxInput($stage);
                        $minOut = $production->stageMinOutput($stage);
```

- [x] **Step 2: Bound the Input and Output fields**

Replace the Input field div (lines 55–58):
```blade
                                <div class="field !mb-0">
                                    <label class="label text-xs">Input</label>
                                    <input type="number" name="input_qty" min="0" max="{{ $maxIn }}" value="{{ $stage->input_qty }}" class="input" {{ $production->status === 'completed' ? 'disabled' : '' }}>
                                    <p class="text-[11px] text-ink-400 mt-0.5">Maks {{ $maxIn }} pcs</p>
                                </div>
```
Replace the Output field div (lines 59–62):
```blade
                                <div class="field !mb-0">
                                    <label class="label text-xs">Output</label>
                                    <input type="number" name="output_qty" min="{{ $minOut }}" value="{{ $stage->output_qty }}" class="input" {{ $production->status === 'completed' ? 'disabled' : '' }}>
                                    @if ($minOut > 0)
                                        <p class="text-[11px] text-ink-400 mt-0.5">Min {{ $minOut }} pcs</p>
                                    @endif
                                </div>
```

- [x] **Step 3: Show a Simpan button on completed stages**

In the buttons block (lines 72–81), add a `completed` branch so finished stages can still be corrected while the batch runs:
```blade
                                <div class="flex gap-2">
                                    @if ($production->status !== 'completed')
                                        @if ($stage->status === 'pending')
                                            <button name="action" value="start" class="btn-secondary text-xs">Mulai</button>
                                        @elseif ($stage->status === 'in_progress')
                                            <button name="action" value="save" class="btn-secondary text-xs">Simpan</button>
                                            <button name="action" value="finish" class="btn-primary text-xs">Selesai</button>
                                        @elseif ($stage->status === 'completed')
                                            <button name="action" value="save" class="btn-secondary text-xs">Simpan</button>
                                        @endif
                                    @endif
                                </div>
```

- [x] **Step 4: Verify nothing broke and smoke-test the page**

Run: `php artisan test`
Expected: PASS (view changes can't break the suite, this is a regression check).

Manual smoke: `php artisan migrate:fresh --seed; php artisan serve`, log in as `admin@labasa.test` / `password`, create a production batch, open `/admin/productions/{id}` and confirm: Input shows "Maks … pcs" per stage; finishing a stage (Selesai) still leaves a Simpan button; entering Input above the max is rejected with the Indonesian message; after finishing packing every field is disabled.

- [x] **Step 5: Commit** — stop and report Task 4 ready for the user to commit (file: `show.blade.php`).

---

## Self-Review Notes (author check — completed)

- **Spec coverage:** §1 auto-SKU → Task 1 (helper, store/update, both forms, MasterDataTest updates). §2 boundaries → Task 2 (helpers) + Task 3 (enforcement) + Task 4 (UI hints). §3 editable completed stages → Task 3 (backend guard scoped to batch, save allowed on completed stage) + Task 4 (Simpan button). Testing section → `ProductSkuTest` (Task 1), `ProductionStageFlowTest` (Task 2), `ProductionFlowTest` (Task 3).
- **Type consistency:** `stageMaxInput(ProductionStage $stage): int` / `stageMinOutput(ProductionStage $stage): int` used identically in Tasks 2, 3, 4; `Product::nextSku(): string` defined and consumed only in Task 1.
- **Known interactions:** pre-existing `ProductionFlowTest::test_completing_packing_credits_stock_and_consumes_materials_once` re-finishes packing after completion — the new guard returns an error flash instead of a silent no-op; its assertions are unaffected (verified against the test body). `MasterDataTest` loses one obsolete test by design.
- **Git constraint:** every "Commit" step defers to the user.
