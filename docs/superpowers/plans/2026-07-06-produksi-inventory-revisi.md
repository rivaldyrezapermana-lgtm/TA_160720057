# Revisi Modul Produksi & Inventaris Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the five production/inventory revisions: overlapping stages with input/output qty and a 50% gate, per-stage machines + recorded material consumption, HPP shown before product price, machine categories mapped to stages, and stock that only moves through transactions.

**Architecture:** Laravel 11 MVC. Migrations add machine categories, per-stage qty/machine columns, a `production_materials` consumption table, and a `products.hpp` snapshot. Business logic (50% gate, batch completion, HPP) lives as small helper methods on the models; controllers orchestrate and views render. Production becomes the transformer that turns material stock into product stock.

**Tech Stack:** PHP 8, Laravel 11, MySQL (prod) / SQLite in-memory (tests, already configured in `phpunit.xml`), Blade + jQuery DataTables + Tailwind CDN. Tests use PHPUnit with `RefreshDatabase`; models are created directly via `Model::create` (only `UserFactory` exists — do not add factories).

## Global Constraints

- User-facing strings are **Bahasa Indonesia** — match existing copy (flash: `->with('success'|'error', '...')`).
- PowerShell shell: chain commands with `;` not `&&`. Never run `git commit`/`git push` — the user handles all git operations. Wherever a task says "Commit", **stage nothing and instead stop and report the task is ready for the user to commit.**
- Follow existing admin patterns: resource controller + `data()` DataTables endpoint + `datatables.{resource}` route; jQuery (not fetch) using the global CSRF setup in `layouts/admin.blade.php`.
- Stage list is fixed: `ProductionStage::STAGES = ['design','sample','cutting','sewing','qc','packing']`.
- Run the full suite with `php artisan test`; a single class with `php artisan test --filter=ClassName`.
- Roles: seeded admin login is `admin@labasa.test` / `password`. In tests create an admin via `User::create([... 'role' => User::ROLE_ADMIN])` and `actingAs()` (see `tests/Feature/Admin/MasterDataTest.php`).

---

## File Structure

**New files:**
- `database/migrations/2026_07_06_000001_create_machine_categories_table.php`
- `database/migrations/2026_07_06_000002_add_machine_category_id_to_production_machines.php`
- `database/migrations/2026_07_06_000003_add_qty_and_machine_to_production_stages.php`
- `database/migrations/2026_07_06_000004_recreate_production_materials_table.php`
- `database/migrations/2026_07_06_000005_add_hpp_to_products_table.php`
- `app/Models/MachineCategory.php`
- `app/Models/ProductionMaterial.php`
- `app/Http/Controllers/Admin/MachineCategoryController.php`
- `resources/views/admin/machine-categories/{index,create,edit}.blade.php`
- `tests/Feature/Admin/MachineCategoryTest.php`
- `tests/Unit/HppTest.php`
- `tests/Unit/ProductionStageFlowTest.php`
- `tests/Feature/Admin/StockLockTest.php`
- `tests/Feature/Admin/ProductionFlowTest.php`

**Modified files:**
- `app/Models/ProductionMachine.php` — `machine_category_id` + `category()` relation.
- `app/Models/ProductionStage.php` — new fillable/casts + `gateQty`/`progressPct` context.
- `app/Models/Production.php` — `productionMaterials()`, `gateQty()`, `stageUnlocked()`.
- `app/Models/Product.php` — `hpp` fillable + `computeHpp()`.
- `app/Http/Controllers/Admin/ProductController.php` — HPP recompute + price warning + ignore `stock` on update.
- `app/Http/Controllers/Admin/MaterialController.php` — ignore `stock` on update.
- `app/Http/Controllers/Admin/ProductionMachineController.php` — category select + validation.
- `app/Http/Controllers/Admin/ProductionController.php` — first-stage input on store, `updateStage` rewrite, completion logic, view data.
- `resources/views/admin/products/{create,edit}.blade.php` — HPP panel + stock lock.
- `resources/views/admin/materials/edit.blade.php` — stock lock.
- `resources/views/admin/production-machines/{create,edit}.blade.php` — category dropdown.
- `resources/views/admin/productions/{show,create}.blade.php` — per-stage qty/machine + bahan panel; remove batch machine select.
- `resources/views/components/admin/sidebar.blade.php` — nav link for machine categories.
- `routes/web.php` — `machine-categories` resource + datatables route.
- `database/seeders/DatabaseSeeder.php` — machine categories + hpp backfill.

---

## Task 1: Machine categories — schema + models

**Files:**
- Create: `database/migrations/2026_07_06_000001_create_machine_categories_table.php`
- Create: `database/migrations/2026_07_06_000002_add_machine_category_id_to_production_machines.php`
- Create: `app/Models/MachineCategory.php`
- Modify: `app/Models/ProductionMachine.php`
- Test: `tests/Unit/ProductionStageFlowTest.php` (temporary relation test, expanded later)

**Interfaces:**
- Produces: `MachineCategory` model (`name, code, stage, notes`; `machines()` hasMany). `ProductionMachine::category()` belongsTo. `machine_categories.stage` holds one of `ProductionStage::STAGES` or null.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/ProductionStageFlowTest.php`:
```php
<?php

namespace Tests\Unit;

use App\Models\MachineCategory;
use App\Models\ProductionMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionStageFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_machine_belongs_to_a_category_mapped_to_a_stage(): void
    {
        $cat = MachineCategory::create(['name' => 'Mesin Potong', 'code' => 'CAT-CUT', 'stage' => 'cutting']);
        $machine = ProductionMachine::create(['name' => 'Cutter A', 'code' => 'M-CUT-A', 'status' => 'active', 'machine_category_id' => $cat->id]);

        $this->assertSame('cutting', $machine->category->stage);
        $this->assertTrue($cat->machines->contains($machine));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ProductionStageFlowTest`
Expected: FAIL — `Class "App\Models\MachineCategory" not found`.

- [ ] **Step 3: Write the migrations and models**

`database/migrations/2026_07_06_000001_create_machine_categories_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('machine_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('code')->unique();
            $t->enum('stage', ['design', 'sample', 'cutting', 'sewing', 'qc', 'packing'])->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_categories');
    }
};
```

`database/migrations/2026_07_06_000002_add_machine_category_id_to_production_machines.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('production_machines', function (Blueprint $t) {
            $t->foreignId('machine_category_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('production_machines', function (Blueprint $t) {
            $t->dropConstrainedForeignId('machine_category_id');
        });
    }
};
```

`app/Models/MachineCategory.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MachineCategory extends Model
{
    protected $fillable = ['name', 'code', 'stage', 'notes'];

    public function machines()
    {
        return $this->hasMany(ProductionMachine::class);
    }
}
```

Modify `app/Models/ProductionMachine.php` — add `machine_category_id` to `$fillable` and add the relation:
```php
    protected $fillable = ['machine_category_id', 'name', 'code', 'status', 'capacity', 'notes'];

    // ... existing STATUSES + productions() ...

    public function category()
    {
        return $this->belongsTo(MachineCategory::class, 'machine_category_id');
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ProductionStageFlowTest`
Expected: PASS.

- [ ] **Step 5: Commit** — stop and report Task 1 ready for the user to commit (files: the two migrations, `MachineCategory.php`, `ProductionMachine.php`, the test).

---

## Task 2: Machine categories — CRUD + machine form dropdown + nav

**Files:**
- Create: `app/Http/Controllers/Admin/MachineCategoryController.php`
- Create: `resources/views/admin/machine-categories/index.blade.php`
- Create: `resources/views/admin/machine-categories/create.blade.php`
- Create: `resources/views/admin/machine-categories/edit.blade.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/Admin/ProductionMachineController.php`
- Modify: `resources/views/admin/production-machines/create.blade.php`
- Modify: `resources/views/admin/production-machines/edit.blade.php`
- Modify: `resources/views/components/admin/sidebar.blade.php`
- Test: `tests/Feature/Admin/MachineCategoryTest.php`

**Interfaces:**
- Consumes: `MachineCategory` (Task 1).
- Produces: routes `admin.machine-categories.{index,create,store,edit,update,destroy}`, `admin.datatables.machine-categories`. `ProductionMachineController::create/edit` pass `$categories` (id⇒name) to the machine forms.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/MachineCategoryTest.php`:
```php
<?php

namespace Tests\Feature\Admin;

use App\Models\MachineCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MachineCategoryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Uji',
            'email' => 'admin.uji@labasa.test',
            'password' => 'password',
            'role' => User::ROLE_ADMIN,
        ]);
    }

    public function test_index_loads(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.machine-categories.index'))
            ->assertOk();
    }

    public function test_category_can_be_created(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.machine-categories.store'), [
                'name' => 'Mesin Jahit', 'code' => 'CAT-SEW', 'stage' => 'sewing',
            ])
            ->assertRedirectToRoute('admin.machine-categories.index');

        $this->assertDatabaseHas('machine_categories', ['code' => 'CAT-SEW', 'stage' => 'sewing']);
    }

    public function test_create_requires_name_and_code(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.machine-categories.store'), ['stage' => 'sewing'])
            ->assertSessionHasErrors(['name', 'code']);
    }

    public function test_category_can_be_deleted_when_unused(): void
    {
        $cat = MachineCategory::create(['name' => 'X', 'code' => 'CAT-X', 'stage' => null]);

        $this->actingAs($this->admin())
            ->delete(route('admin.machine-categories.destroy', $cat))
            ->assertRedirectToRoute('admin.machine-categories.index');

        $this->assertDatabaseMissing('machine_categories', ['id' => $cat->id]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=MachineCategoryTest`
Expected: FAIL — route `admin.machine-categories.index` not defined.

- [ ] **Step 3: Write controller, routes, views, nav, and machine-form dropdown**

`app/Http/Controllers/Admin/MachineCategoryController.php`:
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MachineCategory;
use App\Models\ProductionStage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MachineCategoryController extends Controller
{
    public function index()
    {
        return view('admin.machine-categories.index');
    }

    public function data(Request $request)
    {
        $rows = MachineCategory::withCount('machines')->latest()->get()->map(fn (MachineCategory $c) => [
            'id' => $c->id,
            'code' => $c->code,
            'name' => $c->name,
            'stage' => $c->stage ?? '-',
            'machines' => $c->machines_count,
        ]);

        return response()->json(['data' => $rows]);
    }

    public function create()
    {
        return view('admin.machine-categories.create', ['stages' => ProductionStage::STAGES]);
    }

    public function store(Request $request)
    {
        MachineCategory::create($request->validate($this->rules()));

        return redirect()->route('admin.machine-categories.index')
            ->with('success', 'Kategori mesin berhasil ditambahkan.');
    }

    public function show(MachineCategory $machine_category)
    {
        return redirect()->route('admin.machine-categories.edit', $machine_category);
    }

    public function edit(MachineCategory $machine_category)
    {
        return view('admin.machine-categories.edit', [
            'category' => $machine_category,
            'stages' => ProductionStage::STAGES,
        ]);
    }

    public function update(Request $request, MachineCategory $machine_category)
    {
        $machine_category->update($request->validate($this->rules($machine_category)));

        return redirect()->route('admin.machine-categories.index')
            ->with('success', 'Kategori mesin berhasil diperbarui.');
    }

    public function destroy(MachineCategory $machine_category)
    {
        if ($machine_category->machines()->exists()) {
            return redirect()->route('admin.machine-categories.index')
                ->with('error', 'Kategori tidak bisa dihapus karena masih dipakai mesin.');
        }

        $machine_category->delete();

        return redirect()->route('admin.machine-categories.index')
            ->with('success', 'Kategori mesin berhasil dihapus.');
    }

    private function rules(?MachineCategory $category = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', Rule::unique('machine_categories', 'code')->ignore($category)],
            'stage' => ['nullable', Rule::in(ProductionStage::STAGES)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
```

In `routes/web.php`, add the resource next to `production-machines` (after line 112) and the datatables route inside the `datatables` group (after the `production-machines` line):
```php
    Route::resource('machine-categories', \App\Http\Controllers\Admin\MachineCategoryController::class);
```
```php
        Route::get('machine-categories', [\App\Http\Controllers\Admin\MachineCategoryController::class, 'data'])->name('machine-categories');
```

`resources/views/admin/machine-categories/index.blade.php`:
```blade
@extends('layouts.admin')
@section('title', 'Kategori Mesin')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Kategori Mesin']]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Kategori Mesin</h1>
@endsection

@section('content')
<div class="bg-white border border-ink-100 rounded-xl overflow-hidden">
    <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-ink-100">
        <p class="text-sm text-ink-500">Kelompok mesin menurut fungsi/tahap produksi.</p>
        <a href="{{ route('admin.machine-categories.create') }}" class="btn-primary">+ Tambah Kategori</a>
    </div>
    <div class="p-5">
        <table id="tbl-cats" class="table-clean" style="width:100%">
            <thead><tr><th>Kode</th><th>Nama</th><th>Tahap</th><th class="text-right">Jml Mesin</th><th class="text-right">Aksi</th></tr></thead>
            <tbody></tbody>
        </table>
    </div>
</div>

@push('scripts')
<script>
$(function() {
    const labels = { design:'Desain', sample:'Sample', cutting:'Cutting', sewing:'Sewing', qc:'Quality Check', packing:'Packing' };
    $('#tbl-cats').DataTable({
        ajax: '{{ route("admin.datatables.machine-categories") }}',
        columns: [
            { data: 'code' },
            { data: 'name' },
            { data: 'stage', render: d => labels[d] || d },
            { data: 'machines', className: 'text-right tabular-nums' },
            { data: 'id', className: 'text-right', render: id => `<a href="/admin/machine-categories/${id}/edit" class="text-ink-600 hover:text-ink-900 text-sm">Edit</a>` },
        ],
        pageLength: 10, order: [],
        language: { search: 'Cari:', lengthMenu: 'Tampilkan _MENU_', info: 'Menampilkan _START_-_END_ dari _TOTAL_', zeroRecords: '–', paginate: { previous: '‹', next: '›' } }
    });
});
</script>
@endpush
@endsection
```

`resources/views/admin/machine-categories/create.blade.php`:
```blade
@extends('layouts.admin')
@section('title', 'Tambah Kategori Mesin')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Kategori Mesin', 'url' => route('admin.machine-categories.index')], ['label' => 'Tambah']]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Tambah Kategori Mesin</h1>
@endsection

@section('content')
@php $stageLabels = ['design'=>'Desain','sample'=>'Sample','cutting'=>'Cutting','sewing'=>'Sewing','qc'=>'Quality Check','packing'=>'Packing']; @endphp
<form action="{{ route('admin.machine-categories.store') }}" method="POST" class="max-w-2xl">
    @csrf
    <x-ui.card>
        <div class="grid md:grid-cols-2 gap-4">
            <div class="md:col-span-2"><x-ui.input name="name" label="Nama Kategori" required /></div>
            <x-ui.input name="code" label="Kode" required />
            <x-ui.select name="stage" label="Tahap Produksi" :options="collect($stages)->mapWithKeys(fn($s) => [$s => $stageLabels[$s]])->toArray()" />
            <div class="md:col-span-2"><x-ui.textarea name="notes" label="Catatan" /></div>
        </div>
    </x-ui.card>
    <div class="flex justify-end gap-3 mt-4">
        <a href="{{ route('admin.machine-categories.index') }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">Simpan</button>
    </div>
</form>
@endsection
```

`resources/views/admin/machine-categories/edit.blade.php`:
```blade
@extends('layouts.admin')
@section('title', 'Edit Kategori Mesin')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Kategori Mesin', 'url' => route('admin.machine-categories.index')], ['label' => $category->name]]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Edit: {{ $category->name }}</h1>
@endsection

@section('content')
@php $stageLabels = ['design'=>'Desain','sample'=>'Sample','cutting'=>'Cutting','sewing'=>'Sewing','qc'=>'Quality Check','packing'=>'Packing']; @endphp
<form action="{{ route('admin.machine-categories.update', $category->id) }}" method="POST" class="max-w-2xl">
    @csrf @method('PUT')
    <x-ui.card>
        <div class="grid md:grid-cols-2 gap-4">
            <div class="md:col-span-2"><x-ui.input name="name" label="Nama Kategori" :value="$category->name" required /></div>
            <x-ui.input name="code" label="Kode" :value="$category->code" required />
            <x-ui.select name="stage" label="Tahap Produksi" :selected="$category->stage" :options="collect($stages)->mapWithKeys(fn($s) => [$s => $stageLabels[$s]])->toArray()" />
            <div class="md:col-span-2"><x-ui.textarea name="notes" label="Catatan" :value="$category->notes" /></div>
        </div>
    </x-ui.card>
    <div class="flex justify-end gap-3 mt-4">
        <a href="{{ route('admin.machine-categories.index') }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">Simpan</button>
    </div>
</form>
@endsection
```

In `resources/views/components/admin/sidebar.blade.php`, add a nav link right after the "Mesin Produksi" `</a>` block (after sidebar line ~85):
```blade
        <a href="{{ route('admin.machine-categories.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2 rounded-md text-sm {{ $linkClass('admin.machine-categories.*') }}">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            <span class="sidebar-label">Kategori Mesin</span>
        </a>
```

Update `app/Http/Controllers/Admin/ProductionMachineController.php` — pass categories and validate. Change `create()`, `edit()`, and `rules()`:
```php
    public function create()
    {
        return view('admin.production-machines.create', [
            'categories' => \App\Models\MachineCategory::orderBy('name')->pluck('name', 'id')->toArray(),
        ]);
    }

    public function edit(ProductionMachine $production_machine)
    {
        return view('admin.production-machines.edit', [
            'machine' => $production_machine,
            'categories' => \App\Models\MachineCategory::orderBy('name')->pluck('name', 'id')->toArray(),
        ]);
    }

    private function rules(?ProductionMachine $machine = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', Rule::unique('production_machines', 'code')->ignore($machine)],
            'machine_category_id' => ['nullable', 'exists:machine_categories,id'],
            'status' => ['required', Rule::in(ProductionMachine::STATUSES)],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
```

In `resources/views/admin/production-machines/create.blade.php`, add the category select after the `status` select:
```blade
            <x-ui.select name="machine_category_id" label="Kategori" :options="$categories" />
```

In `resources/views/admin/production-machines/edit.blade.php`, add the same select with the current value bound (place after the status field):
```blade
            <x-ui.select name="machine_category_id" label="Kategori" :selected="$machine->machine_category_id" :options="$categories" />
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=MachineCategoryTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Verify machine form still passes existing suite**

Run: `php artisan test --filter=MasterDataTest`
Expected: PASS (no regressions).

- [ ] **Step 6: Commit** — stop and report Task 2 ready for the user to commit.

---

## Task 3: HPP — compute, store, and warn on product save

**Files:**
- Create: `database/migrations/2026_07_06_000005_add_hpp_to_products_table.php`
- Modify: `app/Models/Product.php`
- Modify: `app/Http/Controllers/Admin/ProductController.php`
- Test: `tests/Unit/HppTest.php`

**Interfaces:**
- Consumes: `ProductMaterial` (existing), `Material.unit_cost`.
- Produces: `Product::computeHpp(): float` = Σ(`qty_required` × `material.unit_cost`). `products.hpp` column, recomputed on `store()`/`update()` after materials sync. Flash `warning` when saved `price < hpp`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/HppTest.php`:
```php
<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Material;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HppTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        $cat = Category::create(['name' => 'Gamis', 'slug' => 'gamis']);
        return Product::create([
            'category_id' => $cat->id, 'name' => 'Gamis A', 'sku' => 'GA-1',
            'price' => 100000, 'stock' => 0, 'is_active' => true,
        ]);
    }

    public function test_compute_hpp_sums_recipe_cost(): void
    {
        $product = $this->product();
        $kain = Material::create(['name' => 'Kain', 'code' => 'K1', 'unit' => 'm', 'stock' => 0, 'min_stock' => 0, 'unit_cost' => 25000]);
        $benang = Material::create(['name' => 'Benang', 'code' => 'B1', 'unit' => 'rol', 'stock' => 0, 'min_stock' => 0, 'unit_cost' => 5000]);
        $product->materials()->create(['material_id' => $kain->id, 'qty_required' => 3]);
        $product->materials()->create(['material_id' => $benang->id, 'qty_required' => 2]);

        // 3*25000 + 2*5000 = 85000
        $this->assertEquals(85000.0, $product->fresh()->computeHpp());
    }

    public function test_compute_hpp_is_zero_without_recipe(): void
    {
        $this->assertEquals(0.0, $this->product()->computeHpp());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=HppTest`
Expected: FAIL — `Call to undefined method App\Models\Product::computeHpp()`.

- [ ] **Step 3: Write migration + model method**

`database/migrations/2026_07_06_000005_add_hpp_to_products_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $t) {
            $t->decimal('hpp', 12, 2)->default(0)->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $t) {
            $t->dropColumn('hpp');
        });
    }
};
```

In `app/Models/Product.php`, add `'hpp'` to `$fillable`, cast it, and add the method:
```php
    protected $fillable = [
        'category_id', 'name', 'sku', 'description',
        'price', 'hpp', 'stock', 'image', 'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'hpp' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // ... existing relations ...

    /** Cost of goods = Σ(qty_required × material unit_cost) over the recipe. */
    public function computeHpp(): float
    {
        return (float) $this->materials()
            ->with('material')
            ->get()
            ->sum(fn ($line) => (float) $line->qty_required * (float) ($line->material->unit_cost ?? 0));
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=HppTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Wire recompute + warning into the controller**

In `app/Http/Controllers/Admin/ProductController.php`, extract the flash into a shared helper and call it from both `store()` and `update()`. Replace the end of `store()` (the `return redirect()...` block) with:
```php
        $this->syncSizes($product, $request->input('sizes', []));
        $this->syncMaterials($product, $request->input('materials', []));
        $this->refreshHpp($product);

        return redirect()->route('admin.products.index')
            ->with('success', 'Produk berhasil ditambahkan.')
            ->with($this->hppWarning($product));
```

Replace the end of `update()` similarly (after `$this->syncMaterials(...)`):
```php
        $this->syncSizes($product, $request->input('sizes', []));
        $this->syncMaterials($product, $request->input('materials', []));
        $this->refreshHpp($product);

        return redirect()->route('admin.products.index')
            ->with('success', 'Produk berhasil diperbarui.')
            ->with($this->hppWarning($product));
```

Add these private helpers to the controller:
```php
    /** Recalculate and persist the product's HPP snapshot from its recipe. */
    private function refreshHpp(Product $product): void
    {
        $product->forceFill(['hpp' => $product->computeHpp()])->save();
    }

    /** Build a flash payload warning when the selling price is below HPP. */
    private function hppWarning(Product $product): array
    {
        if ((float) $product->hpp > 0 && (float) $product->price < (float) $product->hpp) {
            return ['warning' => 'Harga jual (Rp '.number_format((float) $product->price, 0, ',', '.').
                ') di bawah HPP (Rp '.number_format((float) $product->hpp, 0, ',', '.').'). Periksa kembali margin.'];
        }

        return [];
    }
```

Note: `->with([])` is a no-op flash, so the warning key is simply absent when price ≥ HPP.

- [ ] **Step 6: Add a controller test for the warning flash**

Append to `tests/Unit/HppTest.php` a feature-style assertion is awkward in a Unit test; instead create it in `tests/Feature/Admin/StockLockTest.php` later. For now, verify the snapshot is stored by extending the controller through an HTTP test in Task 5's file. Skip here.

- [ ] **Step 7: Run the suite**

Run: `php artisan test --filter=HppTest`
Expected: PASS.

- [ ] **Step 8: Commit** — stop and report Task 3 ready for the user to commit.

---

## Task 4: HPP — live panel on product forms + stock lock UI

**Files:**
- Modify: `resources/views/admin/products/create.blade.php`
- Modify: `resources/views/admin/products/edit.blade.php`

**Interfaces:**
- Consumes: `$materials` already passed to both views (has `id`, `name`, `unit`). Needs `unit_cost` too — update the controller queries.

- [ ] **Step 1: Pass unit_cost to the views**

In `app/Http/Controllers/Admin/ProductController.php`, both `create()` and `edit()` load materials — add `unit_cost` to the selected columns:
```php
        $materials = Material::orderBy('name')->get(['id', 'name', 'unit', 'unit_cost']);
```
(Apply in both methods.)

- [ ] **Step 2: Add the HPP panel + data attributes to `create.blade.php`**

In the "Bahan Baku (Resep)" card, add `data-unit-cost` to each qty input and an id to the checkbox/qty so JS can sum. Replace the qty `<td>` line inside the loop with:
```blade
                        <td><input type="number" min="1" name="materials[{{ $m->id }}][qty_required]" value="{{ old('materials.'.$m->id.'.qty_required') }}" class="input text-right js-bom-qty" data-unit-cost="{{ (float) $m->unit_cost }}" placeholder="0"></td>
```
And add `class="js-bom-use"` to the checkbox input in the same row:
```blade
                                <input type="checkbox" name="materials[{{ $m->id }}][use]" value="1" @checked(old('materials.'.$m->id.'.use')) class="rounded js-bom-use">
```
Immediately after the "Bahan Baku (Resep)" `</x-ui.card>`, add the HPP panel and give the price input an id. First change the price input line in the "Informasi Produk" card to:
```blade
            <x-ui.input name="price" type="number" label="Harga (Rp)" id="price-input" required />
```
Then add before the submit button row:
```blade
    <x-ui.card title="HPP (Harga Pokok)" subtitle="Dihitung otomatis dari resep bahan baku">
        <div class="flex items-center justify-between">
            <p class="text-sm text-ink-500">Total biaya bahan per unit</p>
            <p class="font-display text-2xl font-semibold tabular-nums">Rp <span id="hpp-total">0</span></p>
        </div>
        <p id="hpp-warning" class="hidden mt-3 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
            ⚠ Harga jual di bawah HPP. Periksa kembali margin.
        </p>
    </x-ui.card>

    @push('scripts')
    <script>
    $(function () {
        function fmt(n) { return new Intl.NumberFormat('id-ID').format(Math.round(n)); }
        function recalc() {
            let hpp = 0;
            $('.js-bom-qty').each(function () {
                const $row = $(this).closest('tr');
                const used = $row.find('.js-bom-use').is(':checked');
                const qty = parseFloat($(this).val()) || 0;
                const cost = parseFloat($(this).data('unit-cost')) || 0;
                if (used && qty > 0) hpp += qty * cost;
            });
            $('#hpp-total').text(fmt(hpp));
            const price = parseFloat($('#price-input').val()) || 0;
            $('#hpp-warning').toggleClass('hidden', !(hpp > 0 && price < hpp));
        }
        $(document).on('input change', '.js-bom-qty, .js-bom-use, #price-input', recalc);
        recalc();
    });
    </script>
    @endpush
```

- [ ] **Step 3: Apply the same panel + attributes to `edit.blade.php`**

Make the identical edits in `resources/views/admin/products/edit.blade.php`: add `id="price-input"` to the price input, `class="... js-bom-use"` to each recipe checkbox, `class="input text-right js-bom-qty" data-unit-cost="{{ (float) $m->unit_cost }}"` to each recipe qty input, and paste the same "HPP (Harga Pokok)" card + `@push('scripts')` block before the submit button row.

- [ ] **Step 4: Lock the stock field**

In `create.blade.php` keep the stock field but label it clearly as initial stock (it already reads "Stok Awal" — leave as-is).

In `edit.blade.php`, replace the stock input line:
```blade
            <x-ui.input name="stock" type="number" label="Stok" :value="$product->stock" />
```
with a read-only display (no editable `name` so nothing is submitted):
```blade
            <div class="field">
                <label class="label">Stok</label>
                <input type="number" value="{{ $product->stock }}" class="input bg-ink-50" readonly disabled>
                <p class="field-help">Stok hanya berubah lewat penjualan &amp; produksi.</p>
            </div>
```

- [ ] **Step 5: Manual smoke check**

Run: `php artisan serve` (in a spare terminal), open `/admin/products/create`, check a material, set qty → HPP updates; set price below HPP → warning shows. This step has no automated test (view/JS only); the server-side HPP + warning is covered in Task 3 and Task 5.

- [ ] **Step 6: Commit** — stop and report Task 4 ready for the user to commit.

---

## Task 5: Stock lock — ignore submitted stock on update (product & material)

**Files:**
- Modify: `app/Http/Controllers/Admin/ProductController.php`
- Modify: `app/Http/Controllers/Admin/MaterialController.php`
- Modify: `resources/views/admin/materials/edit.blade.php`
- Test: `tests/Feature/Admin/StockLockTest.php`

**Interfaces:**
- Consumes: existing product/material update routes.
- Produces: `update()` on both controllers preserves the DB `stock` regardless of request input. Also verifies Task 3's HPP snapshot + warning via HTTP.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/StockLockTest.php`:
```php
<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Material;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockLockTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Uji', 'email' => 'admin.uji@labasa.test',
            'password' => 'password', 'role' => User::ROLE_ADMIN,
        ]);
    }

    public function test_product_update_ignores_submitted_stock(): void
    {
        $cat = Category::create(['name' => 'Gamis', 'slug' => 'gamis']);
        $product = Product::create([
            'category_id' => $cat->id, 'name' => 'Gamis A', 'sku' => 'GA-1',
            'price' => 100000, 'stock' => 40, 'is_active' => true,
        ]);

        $this->actingAs($this->admin())
            ->put(route('admin.products.update', $product), [
                'name' => 'Gamis A', 'sku' => 'GA-1', 'category_id' => $cat->id,
                'price' => 120000, 'stock' => 9999, 'is_active' => 1,
            ])
            ->assertRedirect();

        $this->assertEquals(40, $product->fresh()->stock); // unchanged
        $this->assertEquals(120000, (int) $product->fresh()->price); // other fields still update
    }

    public function test_product_update_below_hpp_flashes_warning(): void
    {
        $cat = Category::create(['name' => 'Gamis', 'slug' => 'gamis']);
        $kain = Material::create(['name' => 'Kain', 'code' => 'K1', 'unit' => 'm', 'stock' => 0, 'min_stock' => 0, 'unit_cost' => 25000]);
        $product = Product::create([
            'category_id' => $cat->id, 'name' => 'Gamis A', 'sku' => 'GA-1',
            'price' => 100000, 'stock' => 10, 'is_active' => true,
        ]);

        $this->actingAs($this->admin())
            ->put(route('admin.products.update', $product), [
                'name' => 'Gamis A', 'sku' => 'GA-1', 'category_id' => $cat->id,
                'price' => 50000, 'is_active' => 1,
                'materials' => [$kain->id => ['use' => 1, 'qty_required' => 3]], // HPP = 75000 > 50000
            ])
            ->assertSessionHas('warning');

        $this->assertEquals(75000, (int) $product->fresh()->hpp);
    }

    public function test_material_update_ignores_submitted_stock(): void
    {
        $material = Material::create(['name' => 'Kain', 'code' => 'K1', 'unit' => 'm', 'stock' => 30, 'min_stock' => 5, 'unit_cost' => 25000]);

        $this->actingAs($this->admin())
            ->put(route('admin.materials.update', $material), [
                'name' => 'Kain', 'code' => 'K1', 'unit' => 'm',
                'stock' => 9999, 'min_stock' => 8, 'unit_cost' => 26000,
            ])
            ->assertRedirect();

        $fresh = $material->fresh();
        $this->assertEquals(30, $fresh->stock);      // unchanged
        $this->assertEquals(8, $fresh->min_stock);   // other fields still update
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=StockLockTest`
Expected: FAIL — product/material stock is overwritten to 9999.

- [ ] **Step 3: Make product update ignore stock**

In `app/Http/Controllers/Admin/ProductController.php` `update()`, remove `'stock'` from the `$product->fill([...])` array so it is never overwritten (leave `store()` as-is — initial stock allowed on create). The fill block becomes:
```php
        $product->fill([
            'category_id' => $data['category_id'],
            'name' => $data['name'],
            'sku' => $data['sku'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'is_active' => $request->boolean('is_active'),
        ]);
```
Also remove `'stock'` from `rules()` for update — but `rules()` is shared with `store()` where stock IS allowed. Keep the rule (`'stock' => ['nullable','integer','min:0']`) so create still validates; update simply doesn't read it.

- [ ] **Step 4: Make material update ignore stock**

In `app/Http/Controllers/Admin/MaterialController.php`, change `update()` to exclude `stock`:
```php
    public function update(Request $request, Material $material)
    {
        $data = $request->validate($this->rules($material));
        unset($data['stock']); // stock only moves via purchases/production
        $material->update($data);

        return redirect()->route('admin.materials.index')
            ->with('success', 'Bahan baku berhasil diperbarui.');
    }
```

- [ ] **Step 5: Lock the material stock field in the edit view**

In `resources/views/admin/materials/edit.blade.php`, replace the stock input:
```blade
            <x-ui.input name="stock" type="number" label="Stok" :value="$material->stock" required />
```
with a read-only display:
```blade
            <div class="field">
                <label class="label">Stok</label>
                <input type="number" value="{{ $material->stock }}" class="input bg-ink-50" readonly disabled>
                <p class="field-help">Stok hanya berubah lewat pembelian &amp; produksi.</p>
            </div>
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=StockLockTest`
Expected: PASS (3 tests).

- [ ] **Step 7: Commit** — stop and report Task 5 ready for the user to commit.

---

## Task 6: Production stages — schema + model helpers

**Files:**
- Create: `database/migrations/2026_07_06_000003_add_qty_and_machine_to_production_stages.php`
- Create: `database/migrations/2026_07_06_000004_recreate_production_materials_table.php`
- Create: `app/Models/ProductionMaterial.php`
- Modify: `app/Models/ProductionStage.php`
- Modify: `app/Models/Production.php`
- Test: `tests/Unit/ProductionStageFlowTest.php` (extend)

**Interfaces:**
- Produces:
  - `production_stages` gains `input_qty` (int, default 0), `output_qty` (int, default 0), `production_machine_id` (nullable FK).
  - `production_materials` table (`production_id`, `material_id`, `qty_used`).
  - `ProductionStage::machine()` belongsTo.
  - `Production::gateQty(): int` = `(int) ceil(0.5 * planned_qty)`.
  - `Production::stageUnlocked(ProductionStage $stage): bool` — true if it is the first stage, or the previous stage's `output_qty >= gateQty()`.
  - `Production::productionMaterials()` hasMany.
  - `Production::stageProgressPct(ProductionStage $stage): int`.

- [ ] **Step 1: Write the failing test (extend the existing file)**

Add to `tests/Unit/ProductionStageFlowTest.php` (keep the Task 1 test; add imports for `Category`, `Product`, `Production`, `ProductionStage`, `User`):
```php
    private function batch(int $planned = 100): \App\Models\Production
    {
        $cat = \App\Models\Category::create(['name' => 'Gamis', 'slug' => 'gamis']);
        $product = \App\Models\Product::create([
            'category_id' => $cat->id, 'name' => 'Gamis A', 'sku' => 'GA-1',
            'price' => 100000, 'stock' => 0, 'is_active' => true,
        ]);
        $user = \App\Models\User::create([
            'name' => 'Op', 'email' => 'op@labasa.test', 'password' => 'password', 'role' => \App\Models\User::ROLE_ADMIN,
        ]);
        $prod = \App\Models\Production::create([
            'product_id' => $product->id, 'user_id' => $user->id, 'code' => 'PRD-T-1',
            'planned_qty' => $planned, 'actual_qty' => 0, 'start_date' => now()->toDateString(), 'status' => 'planned',
        ]);
        foreach (\App\Models\ProductionStage::STAGES as $s) {
            $prod->stages()->create(['stage' => $s, 'status' => 'pending', 'input_qty' => 0, 'output_qty' => 0]);
        }
        $prod->stages()->where('stage', 'design')->update(['input_qty' => $planned]);

        return $prod->fresh('stages');
    }

    public function test_gate_qty_is_half_of_planned_rounded_up(): void
    {
        $this->assertSame(50, $this->batch(100)->gateQty());
        $this->assertSame(51, $this->batch(101)->gateQty());
    }

    public function test_first_stage_is_always_unlocked(): void
    {
        $prod = $this->batch(100);
        $design = $prod->stages->firstWhere('stage', 'design');
        $this->assertTrue($prod->stageUnlocked($design));
    }

    public function test_next_stage_locked_until_previous_reaches_gate(): void
    {
        $prod = $this->batch(100);
        $sample = $prod->stages->firstWhere('stage', 'sample');

        $this->assertFalse($prod->stageUnlocked($sample)); // design output 0

        $prod->stages()->where('stage', 'design')->update(['output_qty' => 49]);
        $this->assertFalse($prod->fresh('stages')->stageUnlocked($sample));

        $prod->stages()->where('stage', 'design')->update(['output_qty' => 50]);
        $this->assertTrue($prod->fresh('stages')->stageUnlocked($sample));
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ProductionStageFlowTest`
Expected: FAIL — `input_qty` column / `gateQty()` missing.

- [ ] **Step 3: Write migrations, models, helpers**

`database/migrations/2026_07_06_000003_add_qty_and_machine_to_production_stages.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('production_stages', function (Blueprint $t) {
            $t->integer('input_qty')->default(0)->after('status');
            $t->integer('output_qty')->default(0)->after('input_qty');
            $t->foreignId('production_machine_id')->nullable()->after('output_qty')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('production_stages', function (Blueprint $t) {
            $t->dropConstrainedForeignId('production_machine_id');
            $t->dropColumn(['input_qty', 'output_qty']);
        });
    }
};
```

`database/migrations/2026_07_06_000004_recreate_production_materials_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('production_materials')) {
            Schema::create('production_materials', function (Blueprint $t) {
                $t->id();
                $t->foreignId('production_id')->constrained()->cascadeOnDelete();
                $t->foreignId('material_id')->constrained()->cascadeOnDelete();
                $t->integer('qty_used');
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('production_materials');
    }
};
```

`app/Models/ProductionMaterial.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionMaterial extends Model
{
    protected $fillable = ['production_id', 'material_id', 'qty_used'];

    public function production()
    {
        return $this->belongsTo(Production::class);
    }

    public function material()
    {
        return $this->belongsTo(Material::class);
    }
}
```

Modify `app/Models/ProductionStage.php`:
```php
    protected $fillable = ['production_id', 'stage', 'status', 'input_qty', 'output_qty', 'production_machine_id', 'started_at', 'finished_at', 'notes'];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'input_qty' => 'integer',
        'output_qty' => 'integer',
    ];

    public const STAGES = ['design', 'sample', 'cutting', 'sewing', 'qc', 'packing'];

    public function production() { return $this->belongsTo(Production::class); }

    public function machine() { return $this->belongsTo(ProductionMachine::class, 'production_machine_id'); }
```

Modify `app/Models/Production.php` — add relation and helpers:
```php
    public function stages() { return $this->hasMany(ProductionStage::class); }

    public function productionMaterials() { return $this->hasMany(ProductionMaterial::class); }

    /** Half the planned quantity, rounded up — the handoff threshold. */
    public function gateQty(): int
    {
        return (int) ceil(0.5 * (int) $this->planned_qty);
    }

    /** Progress of a stage as a percentage of planned quantity. */
    public function stageProgressPct(ProductionStage $stage): int
    {
        return $this->planned_qty > 0 ? (int) round($stage->output_qty / $this->planned_qty * 100) : 0;
    }

    /** A stage can start once its predecessor's output reaches the 50% gate. */
    public function stageUnlocked(ProductionStage $stage): bool
    {
        $order = ProductionStage::STAGES;
        $idx = array_search($stage->stage, $order, true);
        if ($idx === false || $idx === 0) {
            return true;
        }

        $prev = $this->stages->firstWhere('stage', $order[$idx - 1]);

        return $prev !== null && (int) $prev->output_qty >= $this->gateQty();
    }
```
Note: `stageUnlocked()` reads the loaded `stages` relation — callers must have `stages` loaded (the show controller and tests load it).

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ProductionStageFlowTest`
Expected: PASS (all tests incl. Task 1's).

- [ ] **Step 5: Commit** — stop and report Task 6 ready for the user to commit.

---

## Task 7: Production stage flow — qty-based updateStage + batch completion

**Files:**
- Modify: `app/Http/Controllers/Admin/ProductionController.php`
- Test: `tests/Feature/Admin/ProductionFlowTest.php`

**Interfaces:**
- Consumes: `Production::gateQty/stageUnlocked` (Task 6), `Product::computeHpp` not needed here; recipe via `product->materials`.
- Produces:
  - `store()` sets the first stage's `input_qty = planned_qty`.
  - `updateStage()` accepts `input_qty`, `output_qty`, `production_machine_id`, `action` (`start`|`save`|`finish`). Enforces `output_qty <= input_qty`, gate on start, and machine-category match.
  - On packing `finish`: sets `production.status='completed'`, `actual_qty = packing.output_qty`, credits `Product.stock`, writes `production_materials` and decrements `Material.stock` — **once** (guarded by status).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/ProductionFlowTest.php`:
```php
<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Material;
use App\Models\Product;
use App\Models\Production;
use App\Models\ProductionStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private function admin(): User
    {
        return $this->user ??= User::create([
            'name' => 'Op', 'email' => 'op@labasa.test', 'password' => 'password', 'role' => User::ROLE_ADMIN,
        ]);
    }

    /** A batch of 100 with a recipe of 3 Kain per unit and 20 starting product stock. */
    private function batch(): array
    {
        $cat = Category::create(['name' => 'Gamis', 'slug' => 'gamis']);
        $kain = Material::create(['name' => 'Kain', 'code' => 'K1', 'unit' => 'm', 'stock' => 500, 'min_stock' => 0, 'unit_cost' => 25000]);
        $product = Product::create([
            'category_id' => $cat->id, 'name' => 'Gamis A', 'sku' => 'GA-1',
            'price' => 100000, 'stock' => 20, 'is_active' => true,
        ]);
        $product->materials()->create(['material_id' => $kain->id, 'qty_required' => 3]);

        $prod = Production::create([
            'product_id' => $product->id, 'user_id' => $this->admin()->id, 'code' => 'PRD-T-1',
            'planned_qty' => 100, 'actual_qty' => 0, 'start_date' => now()->toDateString(), 'status' => 'planned',
        ]);
        foreach (ProductionStage::STAGES as $s) {
            $prod->stages()->create(['stage' => $s, 'status' => 'pending', 'input_qty' => $s === 'design' ? 100 : 0, 'output_qty' => 0]);
        }

        return [$prod->fresh('stages'), $product, $kain];
    }

    public function test_output_cannot_exceed_input(): void
    {
        [$prod] = $this->batch();
        $design = $prod->stages->firstWhere('stage', 'design');

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $design]), [
                'action' => 'save', 'input_qty' => 100, 'output_qty' => 150,
            ])
            ->assertSessionHasErrors('output_qty');
    }

    public function test_next_stage_cannot_start_before_gate(): void
    {
        [$prod] = $this->batch();
        $sample = $prod->stages->firstWhere('stage', 'sample');

        // design output still 0 → sample start rejected
        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $sample]), ['action' => 'start'])
            ->assertSessionHas('error');

        $this->assertDatabaseHas('production_stages', ['id' => $sample->id, 'status' => 'pending']);
    }

    public function test_completing_packing_credits_stock_and_consumes_materials_once(): void
    {
        [$prod, $product, $kain] = $this->batch();
        // Drive every stage output to 100 directly, then finish packing.
        $prod->stages()->update(['output_qty' => 100, 'input_qty' => 100, 'status' => 'in_progress']);
        $packing = $prod->stages()->where('stage', 'packing')->first();

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $packing]), [
                'action' => 'finish', 'input_qty' => 100, 'output_qty' => 100,
            ])
            ->assertRedirect();

        $this->assertEquals('completed', $prod->fresh()->status);
        $this->assertEquals(100, $prod->fresh()->actual_qty);
        $this->assertEquals(120, $product->fresh()->stock);        // 20 + 100
        $this->assertEquals(200, $kain->fresh()->stock);           // 500 - 3*100
        $this->assertDatabaseHas('production_materials', ['production_id' => $prod->id, 'material_id' => $kain->id, 'qty_used' => 300]);

        // Idempotency: finishing again must not double-credit.
        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $packing->fresh()]), [
                'action' => 'finish', 'input_qty' => 100, 'output_qty' => 100,
            ]);

        $this->assertEquals(120, $product->fresh()->stock);        // still 120
        $this->assertEquals(200, $kain->fresh()->stock);           // still 200
        $this->assertEquals(1, $prod->productionMaterials()->count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ProductionFlowTest`
Expected: FAIL — current `updateStage` uses `start`/`finish` toggles without qty and no material consumption.

- [ ] **Step 3: Set the first stage's input on store**

In `app/Http/Controllers/Admin/ProductionController.php` `store()`, replace the stage-seeding loop:
```php
            foreach (ProductionStage::STAGES as $stage) {
                $production->stages()->create([
                    'stage' => $stage,
                    'status' => 'pending',
                ]);
            }
```
with:
```php
            foreach (ProductionStage::STAGES as $i => $stage) {
                $production->stages()->create([
                    'stage' => $stage,
                    'status' => 'pending',
                    'input_qty' => $i === 0 ? (int) $data['planned_qty'] : 0,
                    'output_qty' => 0,
                ]);
            }
```

- [ ] **Step 4: Rewrite `updateStage`**

Replace the whole `updateStage` method in `app/Http/Controllers/Admin/ProductionController.php` with:
```php
    /**
     * Update a stage's input/output quantities and machine, honoring the 50%
     * handoff gate. Finishing the final (packing) stage completes the batch:
     * product stock is credited and recipe materials are consumed — exactly once.
     */
    public function updateStage(Request $request, Production $production, ProductionStage $stage)
    {
        abort_unless($stage->production_id === $production->id, 404);
        $production->load(['stages', 'product.materials']);
        $stage = $production->stages->firstWhere('id', $stage->id);

        $action = $request->input('action', 'save');

        $data = $request->validate([
            'input_qty' => ['nullable', 'integer', 'min:0'],
            'output_qty' => ['nullable', 'integer', 'min:0', 'lte:input_qty'],
            'production_machine_id' => ['nullable', 'exists:production_machines,id'],
        ], [
            'output_qty.lte' => 'Output tidak boleh melebihi input.',
        ]);

        // Machine must belong to the category mapped to this stage.
        if (! empty($data['production_machine_id'])) {
            $machine = ProductionMachine::with('category')->find($data['production_machine_id']);
            if (! $machine || $machine->category?->stage !== $stage->stage) {
                return back()->with('error', 'Mesin yang dipilih tidak sesuai dengan tahap ini.');
            }
        }

        // Gate check when starting a non-first stage.
        if ($action === 'start' && ! $production->stageUnlocked($stage)) {
            return back()->with('error', 'Tahap sebelumnya belum mencapai 50%. Belum bisa dimulai.');
        }

        DB::transaction(function () use ($production, $stage, $action, $data) {
            $stage->fill([
                'input_qty' => $data['input_qty'] ?? $stage->input_qty,
                'output_qty' => $data['output_qty'] ?? $stage->output_qty,
                'production_machine_id' => $data['production_machine_id'] ?? $stage->production_machine_id,
            ]);

            if ($action === 'start' && $stage->status === 'pending') {
                $stage->status = 'in_progress';
                $stage->started_at = now();
                if ($production->status === 'planned') {
                    $production->forceFill(['status' => 'in_progress'])->save();
                }
            }

            if ($action === 'finish') {
                $stage->status = 'completed';
                $stage->finished_at = now();
            }

            $stage->save();

            if ($stage->stage === 'qc' && $action === 'finish' && ! in_array($production->status, ['completed'], true)) {
                $production->forceFill(['status' => 'qc'])->save();
            }

            if ($stage->stage === 'packing' && $action === 'finish' && $production->status !== 'completed') {
                $this->completeBatch($production, $stage);
            }
        });

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Tahap diperbarui.');
    }

    /**
     * Finalize a batch: credit product stock and consume recipe materials.
     * Guarded by status so it can only run once.
     */
    private function completeBatch(Production $production, ProductionStage $packing): void
    {
        $actual = (int) $packing->output_qty;

        $production->forceFill([
            'status' => 'completed',
            'actual_qty' => $actual,
            'end_date' => $production->end_date ?? now()->toDateString(),
        ])->save();

        Product::where('id', $production->product_id)->increment('stock', $actual);

        foreach ($production->product->materials as $line) {
            $used = (int) $line->qty_required * $actual;
            if ($used <= 0) {
                continue;
            }

            $production->productionMaterials()->create([
                'material_id' => $line->material_id,
                'qty_used' => $used,
            ]);

            Material::where('id', $line->material_id)->decrement('stock', $used);
        }
    }
```

Add the needed imports at the top of the controller if missing: `use App\Models\Material;` and `use App\Models\ProductionMachine;`.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=ProductionFlowTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Run the full suite for regressions**

Run: `php artisan test`
Expected: PASS (all).

- [ ] **Step 7: Commit** — stop and report Task 7 ready for the user to commit.

---

## Task 8: Production views — per-stage qty/machine UI + bahan panel; drop batch machine select

**Files:**
- Modify: `app/Http/Controllers/Admin/ProductionController.php` (`show()` + `create()` data)
- Modify: `resources/views/admin/productions/show.blade.php`
- Modify: `resources/views/admin/productions/create.blade.php`

**Interfaces:**
- Consumes: `Production::gateQty/stageUnlocked/stageProgressPct` (Task 6), `MachineCategory` (Task 1).
- Produces: `show()` passes `$machinesByStage` (map stage⇒collection of `id`⇒`name` for machines whose category maps to that stage) and estimated `$bahan` usage.

- [ ] **Step 1: Provide machine + material data to `show()`**

In `app/Http/Controllers/Admin/ProductionController.php` `show()`, replace the body:
```php
    public function show(Production $production)
    {
        $production->load([
            'product.materials.material',
            'machine',
            'stages' => fn ($q) => $q->orderBy('id'),
            'stages.machine',
            'productionMaterials.material',
        ]);

        // Machines available per stage: those whose category maps to the stage.
        $machinesByStage = [];
        foreach (ProductionStage::STAGES as $s) {
            $machinesByStage[$s] = ProductionMachine::where('status', 'active')
                ->whereHas('category', fn ($q) => $q->where('stage', $s))
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray();
        }

        // Estimated material usage from the recipe × planned qty (shown before completion).
        $bahan = $production->product->materials->map(fn ($line) => [
            'name' => $line->material?->name ?? '—',
            'unit' => $line->material?->unit ?? '',
            'per_unit' => (int) $line->qty_required,
            'est' => (int) $line->qty_required * (int) $production->planned_qty,
        ]);

        return view('admin.productions.show', compact('production', 'machinesByStage', 'bahan'));
    }
```

- [ ] **Step 2: Rewrite the "Tahap Produksi" card in `show.blade.php`**

Replace the entire `<x-ui.card title="Tahap Produksi"> ... </x-ui.card>` block with a per-stage form that shows input/output, machine dropdown, progress, and gate state:
```blade
        <x-ui.card title="Tahap Produksi" subtitle="Tahap berikutnya bisa mulai saat tahap sebelumnya mencapai 50%">
            @php
                $labels = ['design'=>'Desain','sample'=>'Sample','cutting'=>'Cutting','sewing'=>'Sewing','qc'=>'Quality Check','packing'=>'Packing'];
                $gate = $production->gateQty();
            @endphp
            <div class="space-y-3">
                @foreach ($production->stages as $i => $stage)
                    @php
                        $unlocked = $production->stageUnlocked($stage);
                        $pct = $production->stageProgressPct($stage);
                        $machines = $machinesByStage[$stage->stage] ?? [];
                        $badge = match($stage->status) {
                            'completed' => ['bg-emerald-50 border-emerald-200', 'Selesai'],
                            'in_progress' => ['bg-ink-50 border-ink-200', 'Berlangsung'],
                            default => ['bg-white border-ink-100', $unlocked ? 'Siap' : 'Terkunci'],
                        };
                    @endphp
                    <div class="border rounded-xl px-4 py-3 {{ $badge[0] }}">
                        <div class="flex items-center justify-between mb-2">
                            <p class="font-medium text-ink-900">{{ $i+1 }}. {{ $labels[$stage->stage] ?? $stage->stage }}</p>
                            <span class="text-xs text-ink-500">{{ $badge[1] }} · {{ $pct }}%</span>
                        </div>
                        <div class="h-1.5 bg-ink-100 rounded-full overflow-hidden mb-3">
                            <div class="h-full bg-ink-900" style="width: {{ $pct }}%"></div>
                        </div>

                        @if (! $unlocked && $stage->status === 'pending')
                            <p class="text-xs text-ink-400">Menunggu tahap sebelumnya mencapai {{ $gate }} pcs (50%).</p>
                        @else
                            <form action="{{ route('admin.productions.stage', [$production->id, $stage->id]) }}" method="POST" class="grid grid-cols-2 md:grid-cols-4 gap-2 items-end">
                                @csrf @method('PATCH')
                                <div class="field !mb-0">
                                    <label class="label text-xs">Input</label>
                                    <input type="number" name="input_qty" min="0" value="{{ $stage->input_qty }}" class="input" {{ $production->status === 'completed' ? 'disabled' : '' }}>
                                </div>
                                <div class="field !mb-0">
                                    <label class="label text-xs">Output</label>
                                    <input type="number" name="output_qty" min="0" value="{{ $stage->output_qty }}" class="input" {{ $production->status === 'completed' ? 'disabled' : '' }}>
                                </div>
                                <div class="field !mb-0">
                                    <label class="label text-xs">Mesin</label>
                                    <select name="production_machine_id" class="input" {{ $production->status === 'completed' ? 'disabled' : '' }}>
                                        <option value="">— Pilih —</option>
                                        @foreach ($machines as $mid => $mname)
                                            <option value="{{ $mid }}" @selected($stage->production_machine_id == $mid)>{{ $mname }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex gap-2">
                                    @if ($production->status !== 'completed')
                                        @if ($stage->status === 'pending')
                                            <button name="action" value="start" class="btn-secondary text-xs">Mulai</button>
                                        @elseif ($stage->status === 'in_progress')
                                            <button name="action" value="save" class="btn-secondary text-xs">Simpan</button>
                                            <button name="action" value="finish" class="btn-primary text-xs">Selesai</button>
                                        @endif
                                    @endif
                                </div>
                            </form>
                            @if (empty($machines) && ! in_array($stage->stage, ['design','sample'], true))
                                <p class="text-xs text-amber-600 mt-1">Belum ada mesin aktif untuk tahap ini. Tambahkan kategori mesin bertahap "{{ $labels[$stage->stage] ?? $stage->stage }}".</p>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>
        </x-ui.card>

        <x-ui.card title="Bahan Terpakai" subtitle="{{ $production->status === 'completed' ? 'Aktual (batch selesai)' : 'Estimasi dari resep × target' }}">
            @php $consumed = $production->productionMaterials; @endphp
            <table class="table-clean">
                <thead><tr><th>Bahan</th><th class="text-right">Per Unit</th><th class="text-right">Total</th><th>Satuan</th></tr></thead>
                <tbody>
                    @if ($production->status === 'completed' && $consumed->isNotEmpty())
                        @foreach ($consumed as $c)
                            <tr>
                                <td>{{ $c->material?->name ?? '—' }}</td>
                                <td class="text-right tabular-nums">-</td>
                                <td class="text-right tabular-nums">{{ number_format($c->qty_used, 0, ',', '.') }}</td>
                                <td class="text-ink-500">{{ $c->material?->unit }}</td>
                            </tr>
                        @endforeach
                    @elseif ($bahan->isNotEmpty())
                        @foreach ($bahan as $b)
                            <tr>
                                <td>{{ $b['name'] }}</td>
                                <td class="text-right tabular-nums">{{ $b['per_unit'] }}</td>
                                <td class="text-right tabular-nums">{{ number_format($b['est'], 0, ',', '.') }}</td>
                                <td class="text-ink-500">{{ $b['unit'] }}</td>
                            </tr>
                        @endforeach
                    @else
                        <tr><td colspan="4" class="text-ink-400 text-center">Produk ini belum punya resep bahan.</td></tr>
                    @endif
                </tbody>
            </table>
        </x-ui.card>
```

- [ ] **Step 3: Remove the batch-level machine select from `create.blade.php`**

Open `resources/views/admin/productions/create.blade.php` and delete the machine `<x-ui.select name="production_machine_id" ...>` field (machines are now chosen per stage). Leave the rest of the form (product, planned_qty, dates, notes) unchanged. The `create()` controller still passes `$machines`; that is now unused but harmless — optionally simplify `create()` to stop querying machines.

- [ ] **Step 4: Smoke test the page**

Run: `php artisan test --filter=ProductionFlowTest` (ensures controller data wiring didn't break routes/queries).
Then manually: `php artisan migrate:fresh --seed`, open a production batch `/admin/productions/{id}`, confirm stages render, locked stages show the gate note, and the Bahan Terpakai panel lists recipe materials.
Expected: PASS + page renders without errors.

- [ ] **Step 5: Commit** — stop and report Task 8 ready for the user to commit.

---

## Task 9: Seeder — machine categories + machine links + HPP backfill

**Files:**
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/Admin/ProductionFlowTest.php` (add a seeder smoke assertion) — optional; primary check is `migrate:fresh --seed`.

**Interfaces:**
- Consumes: everything above.
- Produces: seeded `MachineCategory` rows linked to the 3 existing `ProductionMachine` rows; every seeded product has a correct `hpp`.

- [ ] **Step 1: Read the current seeder to find where machines & products are created**

Run: `php artisan tinker --execute="echo 1;"` is not needed — open `database/seeders/DatabaseSeeder.php` and locate the `ProductionMachine::create(...)` calls and the product-creation loop.

- [ ] **Step 2: Add machine categories and link machines**

In `database/seeders/DatabaseSeeder.php`, before the `ProductionMachine` rows are created, add:
```php
        $catCut = \App\Models\MachineCategory::create(['name' => 'Mesin Potong', 'code' => 'CAT-CUT', 'stage' => 'cutting']);
        $catSew = \App\Models\MachineCategory::create(['name' => 'Mesin Jahit', 'code' => 'CAT-SEW', 'stage' => 'sewing']);
        $catPack = \App\Models\MachineCategory::create(['name' => 'Mesin Packing', 'code' => 'CAT-PACK', 'stage' => 'packing']);
```
Then set `machine_category_id` on the three seeded machines (map the existing three rows to cutting/sewing/packing respectively). For each existing `ProductionMachine::create([...])` add the matching key, e.g.:
```php
        ProductionMachine::create([
            'machine_category_id' => $catCut->id,
            'name' => 'Mesin Potong Kain', 'code' => 'MCH-001', 'status' => 'active', 'capacity' => 200,
        ]);
```
(Preserve the existing names/codes/capacities already in the seeder — only add the `machine_category_id` line, distributing the three categories across the three machines.)

- [ ] **Step 3: Backfill HPP after products + recipes are seeded**

At the end of the seeder's `run()` (after products and their `ProductMaterial` recipes exist), add:
```php
        \App\Models\Product::with('materials.material')->get()->each(function ($product) {
            $product->forceFill(['hpp' => $product->computeHpp()])->save();
        });
```

- [ ] **Step 4: Run the seeder end-to-end**

Run: `php artisan migrate:fresh --seed`
Expected: completes with no errors; `machine_categories` has 3 rows; products have non-zero `hpp` where they have a recipe.

- [ ] **Step 5: Run the full test suite**

Run: `php artisan test`
Expected: PASS (all suites green).

- [ ] **Step 6: Commit** — stop and report Task 9 ready for the user to commit.

---

## Self-Review Notes (author check — completed)

- **Spec coverage:** Rev #1 → Tasks 6–8 (input/output qty + 50% gate + UI). Rev #2 → Task 7 (material consumption + `production_materials`) & Task 8 (per-stage machine + bahan panel). Rev #3 → Tasks 3–4 (HPP compute/store/warn + live panel). Rev #4 → Tasks 1–2 (machine categories + stage mapping) & Task 8 (per-stage filtered dropdown). Rev #5 → Task 5 (stock lock) + Task 4 (read-only UI). All five covered.
- **Consistency:** `gateQty()`, `stageUnlocked()`, `stageProgressPct()`, `computeHpp()`, `completeBatch()`, `refreshHpp()`, `hppWarning()` names are used identically across defining and consuming tasks. `production_materials.qty_used` and `machine_categories.stage` schema/usage match.
- **Git constraint:** every "Commit" step defers to the user (no `git commit` run by the worker), per the standing rule.
