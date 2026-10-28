# Alur Produksi & Fase Sampel — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengganti daftar tahap produksi yang linear dan salah urut menjadi alur berfase Desain → Pola → Sampel (1 pcs) → Massal, dengan gate keras yang melarang produksi massal dimulai sebelum sampel disetujui.

**Architecture:** Tabel `production_stages` dibangun ulang dengan kolom `phase` (`common`/`sample`/`mass`) dan `sort_order`. Nama tahap kini berulang di dua fase, jadi seluruh pencarian tahap sebelum/sesudah di `Production` pindah dari `array_search` atas nama tahap ke `sort_order`. Gate jadi dua jenis: tahap tanpa qty dan tahap fase sampel memakai gate berbasis status, tahap fase massal tetap memakai gate 50% yang sudah ada, dan perpindahan sampel → massal dijaga kolom `productions.sample_approved_at`.

**Tech Stack:** Laravel 11.51, PHP 8.2+, Eloquent, Blade + Tailwind CDN, PHPUnit (SQLite in-memory lewat `phpunit.xml`), MySQL untuk pengembangan.

**Spec:** `docs/superpowers/specs/2026-09-03-alur-produksi-sampel-design.md`

## Global Constraints

- **Bahasa antarmuka Indonesia.** Semua flash message, label tombol, dan pesan validasi ditulis dalam Bahasa Indonesia, mengikuti gaya yang sudah ada di `ProductionController`.
- **PowerShell adalah shell default.** `&&` dan `||` tidak tersedia di Windows PowerShell 5.1 — rantai perintah dengan `;` atau `if ($?) { ... }`.
- **Tidak ada build pipeline.** Tailwind dimuat lewat CDN di `layouts/admin.blade.php`. Jangan menambah `@vite`, `package.json`, atau `node_modules`.
- **Nilai tahap:** `design`, `pola`, `cutting`, `sewing`, `qc_packing`. **Nilai fase:** `common`, `sample`, `mass`. Tidak ada nilai lain di mana pun.
- **Tahap yang membawa qty:** hanya `cutting`, `sewing`, `qc_packing`. `design` dan `pola` selalu `input_qty = 0` dan `output_qty = 0`.
- **Label per fase:** `qc_packing` tampil sebagai **"QC Sampel"** di fase `sample` dan **"QC & Packing"** di fase `mass`.
- **Sampel tidak masuk stok.** `Product.stock` hanya bertambah sebesar `actual_qty`.
- **Bahan terpakai** = `qty_required × (actual_qty + sample_units)`, dengan `sample_units = 0` kalau `planned_qty = 1` dan `1 + sample_revision_count` kalau `planned_qty > 1`.
- **Commit:** pemilik repo meminta commit hanya dilakukan atas permintaan eksplisit. Langkah "Commit" di tiap task disiapkan lengkap, tapi konfirmasi dulu ke pemilik repo sebelum menjalankannya. Jangan pernah menyebut Claude di pesan commit.
- **Format:** jalankan `vendor/bin/pint --dirty` sebelum tiap commit.

---

## File Structure

| File | Tanggung jawab |
|---|---|
| `database/migrations/2026_09_03_000001_restructure_production_stages.php` | Bangun ulang `production_stages` (kolom `phase`, `sort_order`, enum `stage` baru), tambah kolom sampel di `productions`, remap `machine_categories.stage` |
| `database/migrations/2026_09_03_000002_create_production_sample_revisions_table.php` | Tabel riwayat revisi sampel |
| `app/Models/ProductionStage.php` | Daftar nilai tahap/fase, penanda tahap ber-qty, label per fase |
| `app/Models/Production.php` | Urutan tahap lewat `sort_order`, aturan gate, aritmetika sampel |
| `app/Models/ProductionSampleRevision.php` | Satu baris riwayat revisi sampel |
| `app/Http/Controllers/Admin/ProductionController.php` | Pembuatan tahap berfase, transisi tahap, persetujuan/revisi sampel, penyelesaian batch |
| `resources/views/admin/productions/show.blade.php` | Tampilan tahap dikelompokkan per fase, tombol setujui/revisi, riwayat revisi |
| `resources/views/admin/productions/create.blade.php` | Keterangan fase sampel saat target > 1 |
| `resources/views/admin/machine-categories/{create,edit,index}.blade.php` | Label tahap mengikuti daftar baru |
| `database/seeders/DatabaseSeeder.php` | Pemetaan `stage` kategori mesin |
| `tests/Unit/ProductionStageFlowTest.php` | Aturan urutan dan gate di level model |
| `tests/Feature/Admin/ProductionFlowTest.php` | Alur tahap lewat HTTP, stok, dan bahan |
| `tests/Feature/Admin/ProductionSampleTest.php` | Persetujuan dan revisi sampel |

---

### Task 1: Skema berfase, model, dan alur tahap

Task ini satu paket karena berpasangan erat: mengubah `ProductionStage::STAGES` tanpa sekaligus mengubah helper `Production` dan `ProductionController` akan membuat kedua berkas tes yang ada gagal total di tengah jalan. Deliverable-nya: alur berfase berjalan penuh dari pembuatan batch sampai batch selesai, belum termasuk fitur revisi sampel.

**Files:**
- Create: `database/migrations/2026_09_03_000001_restructure_production_stages.php`
- Modify: `app/Models/ProductionStage.php`
- Modify: `app/Models/Production.php`
- Modify: `app/Http/Controllers/Admin/ProductionController.php`
- Test: `tests/Unit/ProductionStageFlowTest.php` (tulis ulang seluruh isi)
- Test: `tests/Feature/Admin/ProductionFlowTest.php` (tulis ulang seluruh isi)

**Interfaces:**
- Produces:
  - `ProductionStage::STAGES: array<string>` = `['design','pola','cutting','sewing','qc_packing']`
  - `ProductionStage::PHASES: array<string>` = `['common','sample','mass']`
  - `ProductionStage::QTY_STAGES: array<string>` = `['cutting','sewing','qc_packing']`
  - `ProductionStage::carriesQty(): bool`
  - `ProductionStage::label(): string`
  - `ProductionStage::stageLabel(string $stage, ?string $phase = null): string` (statis)
  - `Production::hasSamplePhase(): bool`
  - `Production::sampleApproved(): bool`
  - `Production::sampleUnits(): int`
  - `Production::stageUnlocked(ProductionStage $stage): bool`
  - `Production::stageMaxInput(ProductionStage $stage): int`
  - `Production::stageMinOutput(ProductionStage $stage): int`
  - `Production::lockCause(ProductionStage $stage): string` — `'sample_not_approved' | 'gate_50' | 'previous_incomplete'`; satu-satunya tempat sebab penguncian ditentukan, dipakai controller dan view
  - Kolom `productions.sample_approved_at` (nullable timestamp) dan `productions.sample_revision_count` (unsigned int, default 0)

- [ ] **Step 1: Tulis migrasi**

Buat `database/migrations/2026_09_03_000001_restructure_production_stages.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** Nilai tahap lama → tahap baru. Tahap 'sample' dan 'qc' lama tidak punya padanan. */
    private array $stageMap = [
        'design' => 'design',
        'cutting' => 'cutting',
        'sewing' => 'sewing',
        'packing' => 'qc_packing',
    ];

    public function up(): void
    {
        Schema::table('productions', function (Blueprint $t) {
            $t->timestamp('sample_approved_at')->nullable()->after('status');
            $t->unsignedInteger('sample_revision_count')->default(0)->after('sample_approved_at');
        });

        Schema::create('production_stages_new', function (Blueprint $t) {
            $t->id();
            $t->foreignId('production_id')->constrained()->cascadeOnDelete();
            $t->enum('phase', ['common', 'sample', 'mass']);
            $t->unsignedInteger('sort_order');
            $t->enum('stage', ['design', 'pola', 'cutting', 'sewing', 'qc_packing']);
            $t->enum('status', ['pending', 'in_progress', 'completed'])->default('pending');
            $t->integer('input_qty')->default(0);
            $t->integer('output_qty')->default(0);
            $t->foreignId('production_machine_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['production_id', 'sort_order']);
        });

        $this->copyForward();

        Schema::drop('production_stages');
        Schema::rename('production_stages_new', 'production_stages');

        $this->remapMachineCategories([
            'design' => 'design',
            'cutting' => 'cutting',
            'sewing' => 'sewing',
            'qc' => 'qc_packing',
            'packing' => 'qc_packing',
        ], ['design', 'pola', 'cutting', 'sewing', 'qc_packing']);
    }

    /**
     * Salin baris tahap lama ke tabel baru: buang 'sample' dan 'qc', petakan
     * 'packing' → 'qc_packing', dan sisipkan 'pola' tepat setelah 'design'.
     */
    private function copyForward(): void
    {
        $old = DB::table('production_stages')->orderBy('production_id')->orderBy('id')->get();

        foreach ($old->groupBy('production_id') as $productionId => $rows) {
            $order = 0;
            $insert = [];

            foreach ($rows as $row) {
                if (! isset($this->stageMap[$row->stage])) {
                    continue;
                }

                $stage = $this->stageMap[$row->stage];

                $insert[] = [
                    'production_id' => $productionId,
                    'phase' => $stage === 'design' ? 'common' : 'mass',
                    'sort_order' => ++$order,
                    'stage' => $stage,
                    'status' => $row->status,
                    'input_qty' => $row->input_qty,
                    'output_qty' => $row->output_qty,
                    'production_machine_id' => $row->production_machine_id,
                    'started_at' => $row->started_at,
                    'finished_at' => $row->finished_at,
                    'notes' => $row->notes,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ];

                if ($stage === 'design') {
                    $insert[] = [
                        'production_id' => $productionId,
                        'phase' => 'common',
                        'sort_order' => ++$order,
                        'stage' => 'pola',
                        'status' => 'pending',
                        'input_qty' => 0,
                        'output_qty' => 0,
                        'production_machine_id' => null,
                        'started_at' => null,
                        'finished_at' => null,
                        'notes' => null,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ];
                }
            }

            if ($insert !== []) {
                DB::table('production_stages_new')->insert($insert);
            }
        }
    }

    /** Ganti kolom enum `stage` di machine_categories dengan daftar nilai baru. */
    private function remapMachineCategories(array $map, array $allowed): void
    {
        $existing = DB::table('machine_categories')->pluck('stage', 'id');

        Schema::table('machine_categories', function (Blueprint $t) {
            $t->dropColumn('stage');
        });

        Schema::table('machine_categories', function (Blueprint $t) use ($allowed) {
            $t->enum('stage', $allowed)->nullable()->after('code');
        });

        foreach ($existing as $id => $old) {
            DB::table('machine_categories')->where('id', $id)->update([
                'stage' => $old === null ? null : ($map[$old] ?? null),
            ]);
        }
    }

    public function down(): void
    {
        $this->remapMachineCategories(
            ['design' => 'design', 'cutting' => 'cutting', 'sewing' => 'sewing', 'qc_packing' => 'packing'],
            ['design', 'sample', 'cutting', 'sewing', 'qc', 'packing']
        );

        Schema::create('production_stages_old', function (Blueprint $t) {
            $t->id();
            $t->foreignId('production_id')->constrained()->cascadeOnDelete();
            $t->enum('stage', ['design', 'sample', 'cutting', 'sewing', 'qc', 'packing']);
            $t->enum('status', ['pending', 'in_progress', 'completed'])->default('pending');
            $t->integer('input_qty')->default(0);
            $t->integer('output_qty')->default(0);
            $t->foreignId('production_machine_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        $back = ['design' => 'design', 'cutting' => 'cutting', 'sewing' => 'sewing', 'qc_packing' => 'packing'];

        foreach (DB::table('production_stages')->orderBy('id')->get() as $row) {
            if ($row->stage === 'pola' || $row->phase === 'sample') {
                continue;
            }

            DB::table('production_stages_old')->insert([
                'production_id' => $row->production_id,
                'stage' => $back[$row->stage],
                'status' => $row->status,
                'input_qty' => $row->input_qty,
                'output_qty' => $row->output_qty,
                'production_machine_id' => $row->production_machine_id,
                'started_at' => $row->started_at,
                'finished_at' => $row->finished_at,
                'notes' => $row->notes,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        Schema::drop('production_stages');
        Schema::rename('production_stages_old', 'production_stages');

        Schema::table('productions', function (Blueprint $t) {
            $t->dropColumn(['sample_approved_at', 'sample_revision_count']);
        });
    }
};
```

- [ ] **Step 2: Jalankan migrasi di database pengembangan untuk memastikan tidak error**

Run: `php artisan migrate`
Expected: `2026_09_03_000001_restructure_production_stages ... DONE`

Kalau langkah ini gagal karena data lama, jalankan `php artisan migrate:fresh --seed` — spec sudah menyatakan itu jalur yang direkomendasikan.

- [ ] **Step 3: Tulis ulang tes unit (gagal dulu)**

Ganti seluruh isi `tests/Unit/ProductionStageFlowTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\MachineCategory;
use App\Models\Product;
use App\Models\Production;
use App\Models\ProductionMachine;
use App\Models\ProductionStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionStageFlowTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    /** Batch dengan baris tahap berfase, persis seperti yang dibuat controller. */
    private function batch(int $planned = 100): Production
    {
        $n = ++$this->seq;

        $cat = Category::create(['name' => "Gamis {$n}"]);
        $product = Product::create([
            'category_id' => $cat->id, 'name' => "Gamis A {$n}", 'sku' => "GA-{$n}",
            'price' => 100000, 'stock' => 0, 'is_active' => true,
        ]);
        $user = User::create([
            'name' => 'Op', 'email' => "op{$n}@labasa.test", 'password' => 'password', 'role' => User::ROLE_ADMIN,
        ]);
        $prod = Production::create([
            'product_id' => $product->id, 'user_id' => $user->id, 'code' => "PRD-T-{$n}",
            'planned_qty' => $planned, 'actual_qty' => 0, 'start_date' => now()->toDateString(), 'status' => 'planned',
        ]);

        $rows = [['common', 'design', 0], ['common', 'pola', 0]];

        if ($planned > 1) {
            $rows[] = ['sample', 'cutting', 1];
            $rows[] = ['sample', 'sewing', 1];
            $rows[] = ['sample', 'qc_packing', 1];
        }

        $rows[] = ['mass', 'cutting', $planned];
        $rows[] = ['mass', 'sewing', 0];
        $rows[] = ['mass', 'qc_packing', 0];

        $order = 0;
        foreach ($rows as [$phase, $stage, $input]) {
            $prod->stages()->create([
                'phase' => $phase, 'sort_order' => ++$order, 'stage' => $stage,
                'status' => 'pending', 'input_qty' => $input, 'output_qty' => 0,
            ]);
        }

        return $prod->fresh('stages');
    }

    private function stage(Production $p, string $phase, string $stage): ProductionStage
    {
        return $p->stages->first(fn (ProductionStage $s) => $s->phase === $phase && $s->stage === $stage);
    }

    private function complete(Production $p, string $phase, string $stage, int $output = 0): void
    {
        $p->stages()->where('phase', $phase)->where('stage', $stage)
            ->update(['status' => 'completed', 'output_qty' => $output]);
    }

    public function test_machine_belongs_to_a_category_mapped_to_a_stage(): void
    {
        $cat = MachineCategory::create(['name' => 'Mesin Potong', 'code' => 'CAT-CUT', 'stage' => 'cutting']);
        $machine = ProductionMachine::create([
            'name' => 'Cutter A', 'code' => 'M-CUT-A', 'status' => 'active', 'machine_category_id' => $cat->id,
        ]);

        $this->assertSame('cutting', $machine->category->stage);
        $this->assertTrue($cat->machines->contains($machine));
    }

    public function test_batch_over_one_pcs_has_eight_phased_stages(): void
    {
        $prod = $this->batch(50);

        $this->assertSame(
            ['common:design', 'common:pola', 'sample:cutting', 'sample:sewing', 'sample:qc_packing',
                'mass:cutting', 'mass:sewing', 'mass:qc_packing'],
            $prod->stages->sortBy('sort_order')->map(fn ($s) => $s->phase.':'.$s->stage)->values()->all()
        );
        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8], $prod->stages->pluck('sort_order')->sort()->values()->all());
        $this->assertTrue($prod->hasSamplePhase());
    }

    public function test_single_pcs_batch_has_no_sample_phase(): void
    {
        $prod = $this->batch(1);

        $this->assertSame(
            ['common:design', 'common:pola', 'mass:cutting', 'mass:sewing', 'mass:qc_packing'],
            $prod->stages->sortBy('sort_order')->map(fn ($s) => $s->phase.':'.$s->stage)->values()->all()
        );
        $this->assertFalse($prod->hasSamplePhase());
        $this->assertSame(0, $prod->sampleUnits());
    }

    public function test_design_is_always_unlocked(): void
    {
        $prod = $this->batch();

        $this->assertTrue($prod->stageUnlocked($this->stage($prod, 'common', 'design')));
    }

    public function test_pola_is_locked_until_design_is_completed(): void
    {
        $prod = $this->batch();

        $this->assertFalse($prod->stageUnlocked($this->stage($prod, 'common', 'pola')));

        $this->complete($prod, 'common', 'design');
        $prod = $prod->fresh('stages');

        $this->assertTrue($prod->stageUnlocked($this->stage($prod, 'common', 'pola')));
    }

    public function test_sample_cutting_is_locked_until_pola_is_completed(): void
    {
        $prod = $this->batch();

        $this->assertFalse($prod->stageUnlocked($this->stage($prod, 'sample', 'cutting')));

        $this->complete($prod, 'common', 'design');
        $this->complete($prod, 'common', 'pola');
        $prod = $prod->fresh('stages');

        $this->assertTrue($prod->stageUnlocked($this->stage($prod, 'sample', 'cutting')));
    }

    public function test_mass_cutting_is_locked_until_the_sample_is_approved(): void
    {
        $prod = $this->batch(100);
        $this->complete($prod, 'sample', 'qc_packing', 1);
        $prod = $prod->fresh('stages');

        // Tahap QC sampel selesai, tapi belum disetujui.
        $this->assertFalse($prod->stageUnlocked($this->stage($prod, 'mass', 'cutting')));

        $prod->forceFill(['sample_approved_at' => now()])->save();
        $prod = $prod->fresh('stages');

        $this->assertTrue($prod->stageUnlocked($this->stage($prod, 'mass', 'cutting')));
    }

    public function test_mass_cutting_unlocks_after_pola_for_a_single_pcs_batch(): void
    {
        $prod = $this->batch(1);

        $this->assertFalse($prod->stageUnlocked($this->stage($prod, 'mass', 'cutting')));

        $this->complete($prod, 'common', 'pola');
        $prod = $prod->fresh('stages');

        $this->assertTrue($prod->stageUnlocked($this->stage($prod, 'mass', 'cutting')));
    }

    public function test_gate_qty_is_half_of_planned_rounded_up(): void
    {
        $this->assertSame(50, $this->batch(100)->gateQty());
        $this->assertSame(51, $this->batch(101)->gateQty());
    }

    public function test_mass_stages_after_the_first_use_the_fifty_percent_gate(): void
    {
        $prod = $this->batch(100);
        $prod->forceFill(['sample_approved_at' => now()])->save();

        $sewing = fn (Production $p) => $p->stageUnlocked($this->stage($p, 'mass', 'sewing'));

        $this->assertFalse($sewing($prod->fresh('stages')));

        $prod->stages()->where('phase', 'mass')->where('stage', 'cutting')->update(['output_qty' => 49]);
        $this->assertFalse($sewing($prod->fresh('stages')));

        $prod->stages()->where('phase', 'mass')->where('stage', 'cutting')->update(['output_qty' => 50]);
        $this->assertTrue($sewing($prod->fresh('stages')));
    }

    public function test_mass_stage_max_input_is_capped_by_planned_and_previous_output(): void
    {
        $prod = $this->batch(100);

        $this->assertSame(100, $prod->stageMaxInput($this->stage($prod, 'mass', 'cutting')));

        $prod->stages()->where('phase', 'mass')->where('stage', 'cutting')->update(['output_qty' => 60]);
        $prod = $prod->fresh('stages');

        $this->assertSame(60, $prod->stageMaxInput($this->stage($prod, 'mass', 'sewing')));
    }

    public function test_sample_stage_input_is_capped_at_one(): void
    {
        $prod = $this->batch(100);

        $this->assertSame(1, $prod->stageMaxInput($this->stage($prod, 'sample', 'cutting')));
        $this->assertSame(1, $prod->stageMaxInput($this->stage($prod, 'sample', 'qc_packing')));
    }

    public function test_stages_without_qty_report_zero_bounds(): void
    {
        $prod = $this->batch(100);

        $this->assertFalse($this->stage($prod, 'common', 'design')->carriesQty());
        $this->assertFalse($this->stage($prod, 'common', 'pola')->carriesQty());
        $this->assertSame(0, $prod->stageMaxInput($this->stage($prod, 'common', 'pola')));
        $this->assertSame(0, $prod->stageMinOutput($this->stage($prod, 'common', 'design')));
    }

    public function test_mass_stage_min_output_follows_the_next_stage_input(): void
    {
        $prod = $this->batch(100);
        $prod->stages()->where('phase', 'mass')->where('stage', 'sewing')->update(['input_qty' => 50]);
        $prod = $prod->fresh('stages');

        $this->assertSame(50, $prod->stageMinOutput($this->stage($prod, 'mass', 'cutting')));
        $this->assertSame(0, $prod->stageMinOutput($this->stage($prod, 'mass', 'qc_packing')));
    }

    public function test_sample_units_counts_the_sample_plus_every_revision(): void
    {
        $prod = $this->batch(100);
        $this->assertSame(1, $prod->sampleUnits());

        $prod->forceFill(['sample_revision_count' => 2])->save();
        $this->assertSame(3, $prod->fresh()->sampleUnits());
    }

    public function test_lock_cause_matches_the_gate_that_is_actually_blocking(): void
    {
        $mass = $this->batch(100);

        $this->assertSame('previous_incomplete', $mass->lockCause($this->stage($mass, 'common', 'pola')));
        $this->assertSame('previous_incomplete', $mass->lockCause($this->stage($mass, 'sample', 'sewing')));
        $this->assertSame('sample_not_approved', $mass->lockCause($this->stage($mass, 'mass', 'cutting')));
        $this->assertSame('gate_50', $mass->lockCause($this->stage($mass, 'mass', 'sewing')));

        // Batch 1 pcs tidak punya fase sampel: cutting massal menunggu pola, bukan gate 50%.
        $single = $this->batch(1);

        $this->assertSame('previous_incomplete', $single->lockCause($this->stage($single, 'mass', 'cutting')));
        $this->assertSame('gate_50', $single->lockCause($this->stage($single, 'mass', 'sewing')));
    }

    public function test_qc_packing_label_depends_on_the_phase(): void
    {
        $prod = $this->batch(100);

        $this->assertSame('QC Sampel', $this->stage($prod, 'sample', 'qc_packing')->label());
        $this->assertSame('QC & Packing', $this->stage($prod, 'mass', 'qc_packing')->label());
        $this->assertSame('Pembuatan Pola', $this->stage($prod, 'common', 'pola')->label());
    }
}
```

- [ ] **Step 4: Jalankan tes unit untuk memastikan gagal**

Run: `php artisan test --filter=ProductionStageFlowTest`
Expected: FAIL — antara lain `Undefined constant` / kolom `phase` tidak dikenal / `Call to undefined method App\Models\Production::hasSamplePhase()`

- [ ] **Step 5: Perbarui `ProductionStage`**

Ganti isi `app/Models/ProductionStage.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionStage extends Model
{
    protected $fillable = [
        'production_id', 'phase', 'sort_order', 'stage', 'status',
        'input_qty', 'output_qty', 'production_machine_id', 'started_at', 'finished_at', 'notes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'sort_order' => 'integer',
        'input_qty' => 'integer',
        'output_qty' => 'integer',
    ];

    public const STAGES = ['design', 'pola', 'cutting', 'sewing', 'qc_packing'];

    public const PHASES = ['common', 'sample', 'mass'];

    /** Tahap yang membawa jumlah pcs. Desain dan pola tidak menghasilkan barang. */
    public const QTY_STAGES = ['cutting', 'sewing', 'qc_packing'];

    public function production() { return $this->belongsTo(Production::class); }

    public function machine() { return $this->belongsTo(ProductionMachine::class, 'production_machine_id'); }

    public function carriesQty(): bool
    {
        return in_array($this->stage, self::QTY_STAGES, true);
    }

    public function label(): string
    {
        return self::stageLabel($this->stage, $this->phase);
    }

    /** Label tahap. `qc_packing` berbeda antara fase sampel dan fase massal. */
    public static function stageLabel(string $stage, ?string $phase = null): string
    {
        if ($stage === 'qc_packing') {
            return $phase === 'sample' ? 'QC Sampel' : 'QC & Packing';
        }

        return match ($stage) {
            'design' => 'Desain',
            'pola' => 'Pembuatan Pola',
            'cutting' => 'Cutting',
            'sewing' => 'Sewing',
            default => $stage,
        };
    }
}
```

- [ ] **Step 6: Perbarui `Production`**

Di `app/Models/Production.php`, tambahkan dua kolom ke `$fillable` dan `$casts`, lalu ganti keempat helper tahap. Isi lengkap berkas setelah perubahan:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Production extends Model
{
    protected $fillable = [
        'product_id', 'user_id', 'production_machine_id', 'code', 'planned_qty', 'actual_qty',
        'start_date', 'end_date', 'status', 'notes', 'sample_approved_at', 'sample_revision_count',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'sample_approved_at' => 'datetime',
        'sample_revision_count' => 'integer',
    ];

    public const STATUSES = ['planned', 'in_progress', 'qc', 'completed', 'cancelled'];

    public function product() { return $this->belongsTo(Product::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function machine() { return $this->belongsTo(ProductionMachine::class, 'production_machine_id'); }
    public function stages() { return $this->hasMany(ProductionStage::class); }
    public function productionMaterials() { return $this->hasMany(ProductionMaterial::class); }

    /** Batch lebih dari 1 pcs wajib membuat sampel dulu. */
    public function hasSamplePhase(): bool
    {
        return (int) $this->planned_qty > 1;
    }

    public function sampleApproved(): bool
    {
        return $this->sample_approved_at !== null;
    }

    /** Pcs sampel yang memakan bahan: sampel yang disetujui plus tiap putaran revisi. */
    public function sampleUnits(): int
    {
        return $this->hasSamplePhase() ? 1 + (int) $this->sample_revision_count : 0;
    }

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

    private function previousStage(ProductionStage $stage): ?ProductionStage
    {
        return $this->stages->firstWhere('sort_order', (int) $stage->sort_order - 1);
    }

    private function nextStage(ProductionStage $stage): ?ProductionStage
    {
        return $this->stages->firstWhere('sort_order', (int) $stage->sort_order + 1);
    }

    /**
     * Dua jenis gate. Tahap fase massal selain yang pertama memakai ambang 50%;
     * sisanya menunggu tahap sebelumnya selesai. Pintu masuk fase massal dijaga
     * persetujuan sampel.
     */
    public function stageUnlocked(ProductionStage $stage): bool
    {
        $prev = $this->previousStage($stage);

        if ($prev === null) {
            return true;
        }

        if ($stage->phase === 'mass' && $prev->phase !== 'mass') {
            return $this->hasSamplePhase()
                ? $this->sampleApproved()
                : $prev->status === 'completed';
        }

        if ($stage->phase === 'mass') {
            return (int) $prev->output_qty >= $this->gateQty();
        }

        return $prev->status === 'completed';
    }

    /** Batas atas input tahap: target batch, dibatasi output tahap massal sebelumnya. */
    public function stageMaxInput(ProductionStage $stage): int
    {
        if (! $stage->carriesQty()) {
            return 0;
        }

        if ($stage->phase === 'sample') {
            return 1;
        }

        $prev = $this->previousStage($stage);
        $planned = (int) $this->planned_qty;

        if ($prev === null || $prev->phase !== 'mass' || ! $prev->carriesQty()) {
            return $planned;
        }

        return min($planned, (int) $prev->output_qty);
    }

    /** Batas bawah output tahap: jumlah yang sudah diterima tahap berikutnya di fase yang sama. */
    public function stageMinOutput(ProductionStage $stage): int
    {
        if (! $stage->carriesQty() || $stage->phase === 'sample') {
            return 0;
        }

        $next = $this->nextStage($stage);

        if ($next === null || $next->phase !== $stage->phase || ! $next->carriesQty()) {
            return 0;
        }

        return (int) $next->input_qty;
    }

    /**
     * Sebab sebuah tahap masih terkunci: 'sample_not_approved', 'gate_50', atau
     * 'previous_incomplete'. Cabangnya sengaja mengikuti stageUnlocked() supaya
     * pesan yang dibaca operator tidak pernah menyimpang dari aturan gate.
     */
    public function lockCause(ProductionStage $stage): string
    {
        $prev = $this->previousStage($stage);

        if ($prev === null || $stage->phase !== 'mass') {
            return 'previous_incomplete';
        }

        if ($prev->phase !== 'mass') {
            return $this->hasSamplePhase() ? 'sample_not_approved' : 'previous_incomplete';
        }

        return 'gate_50';
    }
}
```

- [ ] **Step 7: Jalankan tes unit untuk memastikan lulus**

Run: `php artisan test --filter=ProductionStageFlowTest`
Expected: PASS — semua tes hijau.

- [ ] **Step 8: Tulis ulang tes feature (gagal dulu)**

Ganti seluruh isi `tests/Feature/Admin/ProductionFlowTest.php`:

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

    private ?User $user = null;

    private function admin(): User
    {
        return $this->user ??= User::create([
            'name' => 'Op', 'email' => 'op@labasa.test', 'password' => 'password', 'role' => User::ROLE_ADMIN,
        ]);
    }

    /**
     * Batch dengan resep 3 m kain per pcs dan stok produk awal 20.
     * Tahap dibuat lewat controller supaya strukturnya sama dengan produksi nyata.
     *
     * @return array{0: Production, 1: Product, 2: Material}
     */
    private function batch(int $planned = 100): array
    {
        $cat = Category::create(['name' => 'Gamis']);
        $kain = Material::create([
            'name' => 'Kain', 'code' => 'K1', 'unit' => 'm', 'stock' => 500, 'min_stock' => 0, 'unit_cost' => 25000,
        ]);
        $product = Product::create([
            'category_id' => $cat->id, 'name' => 'Gamis A', 'sku' => 'GA-1',
            'price' => 100000, 'stock' => 20, 'is_active' => true,
        ]);
        $product->materials()->create(['material_id' => $kain->id, 'qty_required' => 3]);

        $this->actingAs($this->admin())->post(route('admin.productions.store'), [
            'product_id' => $product->id,
            'planned_qty' => $planned,
            'start_date' => now()->toDateString(),
        ]);

        return [Production::latest('id')->first()->fresh('stages'), $product, $kain];
    }

    private function stage(Production $p, string $phase, string $stage): ProductionStage
    {
        return $p->stages()->where('phase', $phase)->where('stage', $stage)->firstOrFail();
    }

    /** Loloskan fase persiapan dan sampel, lalu setujui sampelnya. */
    private function approveSample(Production $p): void
    {
        $p->stages()->where('phase', '!=', 'mass')->update([
            'status' => 'completed', 'output_qty' => 1,
        ]);
        $p->stages()->where('phase', 'common')->update(['output_qty' => 0]);
        $p->forceFill(['sample_approved_at' => now()])->save();
    }

    public function test_store_creates_eight_phased_stages_for_a_mass_batch(): void
    {
        [$prod] = $this->batch(50);

        $this->assertSame(
            ['common:design', 'common:pola', 'sample:cutting', 'sample:sewing', 'sample:qc_packing',
                'mass:cutting', 'mass:sewing', 'mass:qc_packing'],
            $prod->stages->sortBy('sort_order')->map(fn ($s) => $s->phase.':'.$s->stage)->values()->all()
        );
        $this->assertSame(50, $this->stage($prod, 'mass', 'cutting')->input_qty);
        $this->assertSame(1, $this->stage($prod, 'sample', 'cutting')->input_qty);
    }

    public function test_store_skips_the_sample_phase_for_a_single_pcs_batch(): void
    {
        [$prod] = $this->batch(1);

        $this->assertSame(5, $prod->stages->count());
        $this->assertSame(0, $prod->stages()->where('phase', 'sample')->count());
        $this->assertSame(1, $this->stage($prod, 'mass', 'cutting')->input_qty);
    }

    public function test_mass_cutting_cannot_start_before_the_sample_is_approved(): void
    {
        [$prod] = $this->batch();
        $prod->stages()->where('phase', '!=', 'mass')->update(['status' => 'completed', 'output_qty' => 1]);
        $cutting = $this->stage($prod, 'mass', 'cutting');

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $cutting]), ['action' => 'start'])
            ->assertSessionHas('error');

        $this->assertSame('pending', $cutting->fresh()->status);
    }

    public function test_mass_cutting_starts_once_the_sample_is_approved(): void
    {
        [$prod] = $this->batch();
        $this->approveSample($prod);
        $cutting = $this->stage($prod, 'mass', 'cutting');

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $cutting]), ['action' => 'start'])
            ->assertSessionHas('success');

        $this->assertSame('in_progress', $cutting->fresh()->status);
        $this->assertSame('in_progress', $prod->fresh()->status);
    }

    public function test_single_pcs_batch_reports_the_real_reason_mass_cutting_is_locked(): void
    {
        [$prod] = $this->batch(1);
        $cutting = $this->stage($prod, 'mass', 'cutting');

        // Tidak ada fase sampel dan tidak ada gate 50% di sini — yang kurang adalah pola.
        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $cutting]), ['action' => 'start'])
            ->assertSessionHas('error', 'Tahap sebelumnya belum selesai. Belum bisa dimulai.');
    }

    public function test_pola_cannot_start_before_design_is_finished(): void
    {
        [$prod] = $this->batch();
        $pola = $this->stage($prod, 'common', 'pola');

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $pola]), ['action' => 'start'])
            ->assertSessionHas('error');

        $this->assertSame('pending', $pola->fresh()->status);
    }

    public function test_finishing_a_sample_stage_records_one_pcs(): void
    {
        [$prod] = $this->batch();
        $prod->stages()->where('phase', 'common')->update(['status' => 'completed']);
        $cutting = $this->stage($prod, 'sample', 'cutting');

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $cutting]), ['action' => 'start']);
        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $cutting]), ['action' => 'finish']);

        $this->assertSame('completed', $cutting->fresh()->status);
        $this->assertSame(1, $cutting->fresh()->output_qty);
    }

    public function test_output_cannot_exceed_input(): void
    {
        [$prod] = $this->batch();
        $this->approveSample($prod);
        $cutting = $this->stage($prod, 'mass', 'cutting');

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $cutting]), [
                'action' => 'save', 'input_qty' => 100, 'output_qty' => 150,
            ])
            ->assertSessionHasErrors('output_qty');
    }

    public function test_input_cannot_exceed_planned_target(): void
    {
        [$prod] = $this->batch();
        $this->approveSample($prod);
        $cutting = $this->stage($prod, 'mass', 'cutting');

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $cutting]), [
                'action' => 'save', 'input_qty' => 150, 'output_qty' => 0,
            ])
            ->assertSessionHasErrors('input_qty');

        $this->assertSame(100, $cutting->fresh()->input_qty);
    }

    public function test_input_cannot_exceed_previous_stage_output(): void
    {
        [$prod] = $this->batch();
        $this->approveSample($prod);
        $prod->stages()->where('phase', 'mass')->where('stage', 'cutting')
            ->update(['output_qty' => 60, 'status' => 'in_progress']);
        $sewing = $this->stage($prod, 'mass', 'sewing');

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $sewing]), [
                'action' => 'save', 'input_qty' => 70, 'output_qty' => 0,
            ])
            ->assertSessionHasErrors('input_qty');

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $sewing]), [
                'action' => 'save', 'input_qty' => 60, 'output_qty' => 0,
            ])
            ->assertSessionHas('success');

        $this->assertSame(60, $sewing->fresh()->input_qty);
    }

    public function test_output_cannot_drop_below_next_stage_input(): void
    {
        [$prod] = $this->batch();
        $this->approveSample($prod);
        $prod->stages()->where('phase', 'mass')->where('stage', 'cutting')
            ->update(['output_qty' => 60, 'status' => 'in_progress']);
        $prod->stages()->where('phase', 'mass')->where('stage', 'sewing')
            ->update(['input_qty' => 50, 'status' => 'in_progress']);
        $cutting = $this->stage($prod, 'mass', 'cutting');

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $cutting]), [
                'action' => 'save', 'input_qty' => 100, 'output_qty' => 40,
            ])
            ->assertSessionHasErrors('output_qty');

        $this->assertSame(60, $cutting->fresh()->output_qty);
    }

    public function test_starting_mass_qc_packing_moves_the_batch_to_qc_status(): void
    {
        [$prod] = $this->batch();
        $this->approveSample($prod);
        $prod->stages()->where('phase', 'mass')->update(['input_qty' => 100, 'output_qty' => 100, 'status' => 'in_progress']);
        $qc = $this->stage($prod, 'mass', 'qc_packing');
        $qc->update(['status' => 'pending']);

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $qc]), [
                'action' => 'start', 'input_qty' => 100, 'output_qty' => 0,
            ]);

        $this->assertSame('qc', $prod->fresh()->status);
    }

    public function test_finishing_mass_qc_packing_credits_stock_and_consumes_materials_once(): void
    {
        [$prod, $product, $kain] = $this->batch();
        $this->approveSample($prod);
        $prod->stages()->where('phase', 'mass')->update(['input_qty' => 100, 'output_qty' => 100, 'status' => 'in_progress']);
        $qc = $this->stage($prod, 'mass', 'qc_packing');

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $qc]), [
                'action' => 'finish', 'input_qty' => 100, 'output_qty' => 100,
            ])
            ->assertRedirect();

        $this->assertSame('completed', $prod->fresh()->status);
        $this->assertSame(100, $prod->fresh()->actual_qty);
        $this->assertSame(120, $product->fresh()->stock);          // 20 + 100, sampel tidak masuk stok
        $this->assertSame(197, $kain->fresh()->stock);             // 500 - 3*(100 + 1 sampel)
        $this->assertDatabaseHas('production_materials', [
            'production_id' => $prod->id, 'material_id' => $kain->id, 'qty_used' => 303,
        ]);

        // Idempoten: menyelesaikan lagi tidak boleh menambah dua kali.
        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $qc->fresh()]), [
                'action' => 'finish', 'input_qty' => 100, 'output_qty' => 100,
            ]);

        $this->assertSame(120, $product->fresh()->stock);
        $this->assertSame(197, $kain->fresh()->stock);
        $this->assertSame(1, $prod->productionMaterials()->count());
    }

    public function test_single_pcs_batch_consumes_no_extra_sample_material(): void
    {
        [$prod, $product, $kain] = $this->batch(1);
        $prod->stages()->where('phase', 'common')->update(['status' => 'completed']);
        $prod->stages()->where('phase', 'mass')->update(['input_qty' => 1, 'output_qty' => 1, 'status' => 'in_progress']);
        $qc = $this->stage($prod, 'mass', 'qc_packing');

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $qc]), [
                'action' => 'finish', 'input_qty' => 1, 'output_qty' => 1,
            ]);

        $this->assertSame(21, $product->fresh()->stock);   // 20 + 1
        $this->assertSame(497, $kain->fresh()->stock);     // 500 - 3*1
    }

    public function test_completed_stage_can_still_be_edited_until_batch_completes(): void
    {
        [$prod] = $this->batch();
        $this->approveSample($prod);
        $prod->stages()->where('phase', 'mass')->where('stage', 'cutting')
            ->update(['input_qty' => 100, 'output_qty' => 90, 'status' => 'completed']);
        $cutting = $this->stage($prod, 'mass', 'cutting');

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $cutting]), [
                'action' => 'save', 'input_qty' => 100, 'output_qty' => 85,
            ])
            ->assertSessionHas('success');

        $this->assertSame(85, $cutting->fresh()->output_qty);
        $this->assertSame('completed', $cutting->fresh()->status);
    }

    public function test_stages_locked_after_batch_completed(): void
    {
        [$prod] = $this->batch();
        $this->approveSample($prod);
        $prod->stages()->where('phase', 'mass')->update(['input_qty' => 100, 'output_qty' => 100, 'status' => 'in_progress']);
        $qc = $this->stage($prod, 'mass', 'qc_packing');

        $this->actingAs($this->admin())->patch(route('admin.productions.stage', [$prod, $qc]), [
            'action' => 'finish', 'input_qty' => 100, 'output_qty' => 100,
        ]);
        $this->assertSame('completed', $prod->fresh()->status);

        $cutting = $this->stage($prod, 'mass', 'cutting');
        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $cutting]), [
                'action' => 'save', 'input_qty' => 100, 'output_qty' => 50,
            ])
            ->assertSessionHas('error');

        $this->assertSame(100, $cutting->fresh()->output_qty);
    }

    public function test_completed_batch_cannot_be_edited(): void
    {
        [$prod] = $this->batch();
        $prod->forceFill(['status' => 'completed'])->save();

        $this->actingAs($this->admin())
            ->put(route('admin.productions.update', $prod), [
                'planned_qty' => 100, 'start_date' => now()->toDateString(), 'status' => 'in_progress',
            ])
            ->assertSessionHas('error');

        $this->assertSame('completed', $prod->fresh()->status);
    }

    public function test_planned_qty_cannot_drop_below_recorded_stage_input(): void
    {
        [$prod] = $this->batch(); // mass cutting sudah punya input_qty = 100

        $this->actingAs($this->admin())
            ->put(route('admin.productions.update', $prod), [
                'planned_qty' => 50, 'start_date' => now()->toDateString(), 'status' => 'in_progress',
            ])
            ->assertSessionHasErrors('planned_qty');

        $this->assertSame(100, $prod->fresh()->planned_qty);
    }
}
```

- [ ] **Step 9: Jalankan tes feature untuk memastikan gagal**

Run: `php artisan test --filter=ProductionFlowTest`
Expected: FAIL — `store()` masih membuat tahap versi lama, dan `completeBatch` masih mencari tahap bernama `packing`.

- [ ] **Step 10: Perbarui `ProductionController@store`**

Di `app/Http/Controllers/Admin/ProductionController.php`, ganti blok `foreach (ProductionStage::STAGES as $i => $stage) { ... }` di dalam transaksi `store()` dengan:

```php
            foreach ($this->stageBlueprint((int) $data['planned_qty']) as $row) {
                $production->stages()->create($row + ['status' => 'pending', 'output_qty' => 0]);
            }
```

Lalu tambahkan method privat berikut di kelas yang sama, tepat setelah `store()`:

```php
    /**
     * Susunan tahap sebuah batch. Fase sampel hanya ada kalau target lebih dari
     * 1 pcs — batch 1 pcs memang produknya sekaligus sampelnya.
     *
     * @return list<array{phase: string, sort_order: int, stage: string, input_qty: int}>
     */
    private function stageBlueprint(int $plannedQty): array
    {
        $rows = [
            ['phase' => 'common', 'stage' => 'design', 'input_qty' => 0],
            ['phase' => 'common', 'stage' => 'pola', 'input_qty' => 0],
        ];

        if ($plannedQty > 1) {
            $rows[] = ['phase' => 'sample', 'stage' => 'cutting', 'input_qty' => 1];
            $rows[] = ['phase' => 'sample', 'stage' => 'sewing', 'input_qty' => 1];
            $rows[] = ['phase' => 'sample', 'stage' => 'qc_packing', 'input_qty' => 1];
        }

        $rows[] = ['phase' => 'mass', 'stage' => 'cutting', 'input_qty' => $plannedQty];
        $rows[] = ['phase' => 'mass', 'stage' => 'sewing', 'input_qty' => 0];
        $rows[] = ['phase' => 'mass', 'stage' => 'qc_packing', 'input_qty' => 0];

        foreach ($rows as $i => $row) {
            $rows[$i]['sort_order'] = $i + 1;
        }

        return $rows;
    }
```

- [ ] **Step 11: Perbarui `ProductionController@updateStage`**

Ganti seluruh method `updateStage()` dengan versi berikut. Perubahannya: validasi qty hanya berlaku untuk tahap ber-qty di fase massal, tahap fase sampel selalu mencatat 1 pcs saat selesai, pesan penguncian menjelaskan sebabnya, dan pemicu penyelesaian batch pindah ke `qc_packing` fase massal.

```php
    /**
     * Update a stage's quantities and machine. Tahap fase massal tunduk pada gate
     * 50%; tahap lain menunggu tahap sebelumnya selesai. Menyelesaikan QC & Packing
     * fase massal menutup batch: stok produk bertambah dan bahan resep dipotong,
     * tepat satu kali.
     */
    public function updateStage(Request $request, Production $production, ProductionStage $stage)
    {
        abort_unless($stage->production_id === $production->id, 404);
        $production->load(['stages', 'product.materials']);
        $stage = $production->stages->firstWhere('id', $stage->id);

        if ($production->status === 'completed') {
            return back()->with('error', 'Batch sudah selesai. Tahapan tidak bisa diubah lagi.');
        }

        $action = $request->input('action', 'save');
        $qtyEditable = $stage->carriesQty() && $stage->phase === 'mass';

        if ($qtyEditable) {
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
        } else {
            $data = $request->validate([
                'production_machine_id' => ['nullable', 'exists:production_machines,id'],
            ]);
        }

        // Machine must belong to the category mapped to this stage.
        if (! empty($data['production_machine_id'])) {
            $machine = ProductionMachine::with('category')->find($data['production_machine_id']);
            if (! $machine || $machine->category?->stage !== $stage->stage) {
                return back()->with('error', 'Mesin yang dipilih tidak sesuai dengan tahap ini.');
            }
        }

        if ($action === 'start' && ! $production->stageUnlocked($stage)) {
            return back()->with('error', $this->lockReason($production, $stage));
        }

        DB::transaction(function () use ($production, $stage, $action, $data, $qtyEditable) {
            if ($qtyEditable) {
                $stage->input_qty = $data['input_qty'] ?? $stage->input_qty;
                $stage->output_qty = $data['output_qty'] ?? $stage->output_qty;
            }

            $stage->production_machine_id = $data['production_machine_id'] ?? $stage->production_machine_id;

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

                // Fase sampel selalu satu potong.
                if ($stage->phase === 'sample') {
                    $stage->input_qty = 1;
                    $stage->output_qty = 1;
                }
            }

            $stage->save();

            $isMassQc = $stage->phase === 'mass' && $stage->stage === 'qc_packing';

            if ($isMassQc && $action === 'start' && $production->status !== 'completed') {
                $production->forceFill(['status' => 'qc'])->save();
            }

            if ($isMassQc && $action === 'finish' && $production->status !== 'completed') {
                $this->completeBatch($production, $stage);
            }
        });

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Tahap diperbarui.');
    }

    /** Alasan sebuah tahap masih terkunci, dalam kalimat yang bisa dibaca operator. */
    private function lockReason(Production $production, ProductionStage $stage): string
    {
        return match ($production->lockCause($stage)) {
            'sample_not_approved' => 'Sampel belum disetujui. Selesaikan dan setujui sampel sebelum memulai produksi massal.',
            'gate_50' => 'Tahap sebelumnya belum mencapai 50%. Belum bisa dimulai.',
            default => 'Tahap sebelumnya belum selesai. Belum bisa dimulai.',
        };
    }
```

- [ ] **Step 12: Perbarui `completeBatch` agar menghitung bahan sampel**

Di method `completeBatch()`, ganti parameter dan perhitungan bahan:

```php
    /**
     * Finalize a batch: credit product stock and consume recipe materials.
     * Sampel memakan bahan tapi tidak masuk stok jual.
     * Guarded by status so it can only run once.
     */
    private function completeBatch(Production $production, ProductionStage $qcPacking): void
    {
        $actual = (int) $qcPacking->output_qty;

        $production->forceFill([
            'status' => 'completed',
            'actual_qty' => $actual,
            'end_date' => $production->end_date ?? now()->toDateString(),
        ])->save();

        Product::where('id', $production->product_id)->increment('stock', $actual);

        $units = $actual + $production->sampleUnits();

        foreach ($production->product->materials as $line) {
            $used = (int) $line->qty_required * $units;
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

- [ ] **Step 13: Jalankan kedua berkas tes**

Run: `php artisan test --filter="ProductionFlowTest|ProductionStageFlowTest"`
Expected: PASS — semua hijau.

- [ ] **Step 14: Jalankan seluruh suite untuk melihat kerusakan lain**

Run: `php artisan test`
Expected: PASS untuk semua kecuali kemungkinan `MachineCategoryTest` — berkas itu hanya memakai `'sewing'` yang masih valid, jadi seharusnya ikut hijau. Kalau ada tes lain yang merah, perbaiki di task ini sebelum lanjut.

- [ ] **Step 15: Commit** (konfirmasi dulu ke pemilik repo)

```bash
vendor/bin/pint --dirty
git add database/migrations/2026_09_03_000001_restructure_production_stages.php app/Models/ProductionStage.php app/Models/Production.php app/Http/Controllers/Admin/ProductionController.php tests/Unit/ProductionStageFlowTest.php tests/Feature/Admin/ProductionFlowTest.php
git commit -m "Restructure production stages into common, sample and mass phases"
```

---

### Task 2: Persetujuan dan revisi sampel

**Files:**
- Create: `database/migrations/2026_09_03_000002_create_production_sample_revisions_table.php`
- Create: `app/Models/ProductionSampleRevision.php`
- Modify: `app/Models/Production.php` (tambah relasi `sampleRevisions()`)
- Modify: `app/Http/Controllers/Admin/ProductionController.php`
- Test: `tests/Feature/Admin/ProductionSampleTest.php`

**Interfaces:**
- Consumes: `Production::hasSamplePhase()`, `Production::sampleApproved()`, `ProductionStage::$phase`, `ProductionStage::$stage` (Task 1)
- Produces:
  - `ProductionSampleRevision` dengan kolom `production_id`, `revision_no`, `notes`, `user_id`, `created_at`
  - `Production::sampleRevisions(): HasMany` (urut `revision_no` menurun)
  - Aksi form `approve_sample` dan `revise_sample` di route `admin.productions.stage`

- [ ] **Step 1: Tulis migrasi tabel revisi**

Buat `database/migrations/2026_09_03_000002_create_production_sample_revisions_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('production_sample_revisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('production_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('revision_no');
            $t->text('notes');
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('created_at')->nullable();
            $t->unique(['production_id', 'revision_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_sample_revisions');
    }
};
```

- [ ] **Step 2: Jalankan migrasi**

Run: `php artisan migrate`
Expected: `2026_09_03_000002_create_production_sample_revisions_table ... DONE`

- [ ] **Step 3: Tulis tes feature (gagal dulu)**

Buat `tests/Feature/Admin/ProductionSampleTest.php`:

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

class ProductionSampleTest extends TestCase
{
    use RefreshDatabase;

    private ?User $user = null;

    private function admin(): User
    {
        return $this->user ??= User::create([
            'name' => 'Op', 'email' => 'op@labasa.test', 'password' => 'password', 'role' => User::ROLE_ADMIN,
        ]);
    }

    private function batch(int $planned = 100): Production
    {
        $cat = Category::create(['name' => 'Gamis']);
        $kain = Material::create([
            'name' => 'Kain', 'code' => 'K1', 'unit' => 'm', 'stock' => 500, 'min_stock' => 0, 'unit_cost' => 25000,
        ]);
        $product = Product::create([
            'category_id' => $cat->id, 'name' => 'Gamis A', 'sku' => 'GA-1',
            'price' => 100000, 'stock' => 0, 'is_active' => true,
        ]);
        $product->materials()->create(['material_id' => $kain->id, 'qty_required' => 3]);

        $this->actingAs($this->admin())->post(route('admin.productions.store'), [
            'product_id' => $product->id,
            'planned_qty' => $planned,
            'start_date' => now()->toDateString(),
        ]);

        return Production::latest('id')->first()->fresh('stages');
    }

    private function sampleQc(Production $p): ProductionStage
    {
        return $p->stages()->where('phase', 'sample')->where('stage', 'qc_packing')->firstOrFail();
    }

    /** Loloskan persiapan dan fase sampel sampai QC sampel selesai. */
    private function finishSampleWork(Production $p): void
    {
        $p->stages()->where('phase', 'common')->update(['status' => 'completed']);
        $p->stages()->where('phase', 'sample')->update([
            'status' => 'completed', 'input_qty' => 1, 'output_qty' => 1,
        ]);
    }

    public function test_approving_the_sample_opens_the_mass_phase(): void
    {
        $prod = $this->batch();
        $this->finishSampleWork($prod);

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $this->sampleQc($prod)]), [
                'action' => 'approve_sample',
            ])
            ->assertSessionHas('success');

        $this->assertNotNull($prod->fresh()->sample_approved_at);

        $cutting = $prod->stages()->where('phase', 'mass')->where('stage', 'cutting')->firstOrFail();
        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $cutting]), ['action' => 'start'])
            ->assertSessionHas('success');
    }

    public function test_sample_cannot_be_approved_before_its_qc_stage_is_finished(): void
    {
        $prod = $this->batch();
        $prod->stages()->where('phase', 'common')->update(['status' => 'completed']);

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $this->sampleQc($prod)]), [
                'action' => 'approve_sample',
            ])
            ->assertSessionHas('error');

        $this->assertNull($prod->fresh()->sample_approved_at);
    }

    public function test_approval_is_rejected_on_a_stage_that_is_not_sample_qc(): void
    {
        $prod = $this->batch();
        $this->finishSampleWork($prod);
        $massCutting = $prod->stages()->where('phase', 'mass')->where('stage', 'cutting')->firstOrFail();

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $massCutting]), ['action' => 'approve_sample'])
            ->assertSessionHas('error');

        $this->assertNull($prod->fresh()->sample_approved_at);
    }

    public function test_approval_is_rejected_on_the_mass_phase_qc_packing_stage(): void
    {
        $prod = $this->batch();
        $this->finishSampleWork($prod);
        $massQc = $prod->stages()->where('phase', 'mass')->where('stage', 'qc_packing')->firstOrFail();

        // Nama tahapnya sama dengan QC sampel — yang membedakan hanya fasenya.
        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $massQc]), ['action' => 'approve_sample'])
            ->assertSessionHas('error');

        $this->assertNull($prod->fresh()->sample_approved_at);
    }

    public function test_revision_requires_a_note(): void
    {
        $prod = $this->batch();
        $this->finishSampleWork($prod);

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $this->sampleQc($prod)]), [
                'action' => 'revise_sample', 'notes' => '',
            ])
            ->assertSessionHasErrors('notes');

        $this->assertSame(0, $prod->fresh()->sample_revision_count);
    }

    public function test_revision_reopens_the_sample_phase_and_records_history(): void
    {
        $prod = $this->batch();
        $this->finishSampleWork($prod);

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $this->sampleQc($prod)]), [
                'action' => 'revise_sample', 'notes' => 'Panjang lengan kurang 2cm',
            ])
            ->assertSessionHas('success');

        $prod = $prod->fresh('stages');

        $this->assertSame(1, $prod->sample_revision_count);
        $this->assertNull($prod->sample_approved_at);
        $this->assertSame(3, $prod->stages()->where('phase', 'sample')->where('status', 'pending')->count());
        $this->assertSame(0, (int) $prod->stages()->where('phase', 'sample')->max('output_qty'));
        $this->assertDatabaseHas('production_sample_revisions', [
            'production_id' => $prod->id, 'revision_no' => 1, 'notes' => 'Panjang lengan kurang 2cm',
        ]);

        // Fase massal terkunci lagi.
        $cutting = $prod->stages()->where('phase', 'mass')->where('stage', 'cutting')->firstOrFail();
        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $cutting]), ['action' => 'start'])
            ->assertSessionHas('error');
    }

    public function test_second_revision_increments_the_revision_number(): void
    {
        $prod = $this->batch();

        foreach (['Panjang lengan kurang 2cm', 'Resleting seret'] as $note) {
            $this->finishSampleWork($prod);
            $this->actingAs($this->admin())
                ->patch(route('admin.productions.stage', [$prod, $this->sampleQc($prod)]), [
                    'action' => 'revise_sample', 'notes' => $note,
                ]);
        }

        $this->assertSame(2, $prod->fresh()->sample_revision_count);
        $this->assertSame([2, 1], $prod->fresh()->sampleRevisions->pluck('revision_no')->all());
    }

    public function test_revisions_add_their_own_material_consumption(): void
    {
        $prod = $this->batch();
        $kain = Material::where('code', 'K1')->firstOrFail();
        $product = Product::where('sku', 'GA-1')->firstOrFail();

        // Dua kali revisi sebelum sampel akhirnya disetujui.
        foreach (['Revisi satu', 'Revisi dua'] as $note) {
            $this->finishSampleWork($prod);
            $this->actingAs($this->admin())
                ->patch(route('admin.productions.stage', [$prod, $this->sampleQc($prod)]), [
                    'action' => 'revise_sample', 'notes' => $note,
                ]);
        }

        $this->finishSampleWork($prod);
        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $this->sampleQc($prod)]), ['action' => 'approve_sample']);

        $prod = $prod->fresh('stages');
        $prod->stages()->where('phase', 'mass')->update(['input_qty' => 100, 'output_qty' => 100, 'status' => 'in_progress']);
        $massQc = $prod->stages()->where('phase', 'mass')->where('stage', 'qc_packing')->firstOrFail();

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $massQc]), [
                'action' => 'finish', 'input_qty' => 100, 'output_qty' => 100,
            ]);

        // 100 massal + 1 sampel + 2 revisi = 103 pcs × 3 m = 309 m.
        $this->assertSame(100, $product->fresh()->stock);
        $this->assertSame(191, $kain->fresh()->stock);   // 500 - 309
        $this->assertDatabaseHas('production_materials', [
            'production_id' => $prod->id, 'material_id' => $kain->id, 'qty_used' => 309,
        ]);
    }
}
```

- [ ] **Step 4: Jalankan tes untuk memastikan gagal**

Run: `php artisan test --filter=ProductionSampleTest`
Expected: FAIL — aksi `approve_sample` belum dikenal, jadi controller memperlakukannya sebagai `save` biasa dan `sample_approved_at` tetap null.

- [ ] **Step 5: Buat model `ProductionSampleRevision`**

Buat `app/Models/ProductionSampleRevision.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionSampleRevision extends Model
{
    /** Baris riwayat tidak pernah diubah, jadi tidak butuh updated_at. */
    public const UPDATED_AT = null;

    protected $fillable = ['production_id', 'revision_no', 'notes', 'user_id'];

    protected $casts = [
        'revision_no' => 'integer',
        'created_at' => 'datetime',
    ];

    public function production() { return $this->belongsTo(Production::class); }

    public function user() { return $this->belongsTo(User::class); }
}
```

- [ ] **Step 6: Tambah relasi di `Production`**

Di `app/Models/Production.php`, tambahkan tepat setelah `productionMaterials()`:

```php
    public function sampleRevisions()
    {
        return $this->hasMany(ProductionSampleRevision::class)->orderByDesc('revision_no');
    }
```

- [ ] **Step 7: Tambah kedua aksi di controller**

Di `app/Http/Controllers/Admin/ProductionController.php`, sisipkan di awal `updateStage()` — tepat setelah baris `$action = $request->input('action', 'save');`:

```php
        if (in_array($action, ['approve_sample', 'revise_sample'], true)) {
            if ($stage->phase !== 'sample' || $stage->stage !== 'qc_packing') {
                return back()->with('error', 'Aksi ini hanya berlaku untuk tahap QC Sampel.');
            }

            return $action === 'approve_sample'
                ? $this->approveSample($production, $stage)
                : $this->reviseSample($request, $production);
        }
```

Lalu tambahkan dua method privat berikut, tepat setelah `lockReason()`:

```php
    /** Sampel disetujui: fase massal terbuka. */
    private function approveSample(Production $production, ProductionStage $sampleQc)
    {
        if ($sampleQc->status !== 'completed') {
            return back()->with('error', 'Selesaikan tahap QC Sampel dulu sebelum menyetujui sampel.');
        }

        $production->forceFill(['sample_approved_at' => now()])->save();

        return back()->with('success', 'Sampel disetujui. Produksi massal bisa dimulai.');
    }

    /**
     * Sampel perlu diperbaiki: catat alasannya dan buka kembali seluruh tahap
     * fase sampel supaya satu potong dibuat ulang.
     */
    private function reviseSample(Request $request, Production $production)
    {
        $data = $request->validate([
            'notes' => ['required', 'string', 'max:1000'],
        ], [
            'notes.required' => 'Catatan revisi wajib diisi.',
        ]);

        DB::transaction(function () use ($production, $data) {
            // Kunci baris batch dulu. Dua klik "Perlu revisi" beruntun tidak boleh
            // menghitung nomor revisi yang sama dan menabrak unique constraint
            // (production_id, revision_no) dengan error mentah.
            $locked = Production::whereKey($production->getKey())->lockForUpdate()->firstOrFail();
            $revisionNo = (int) $locked->sample_revision_count + 1;

            $locked->sampleRevisions()->create([
                'revision_no' => $revisionNo,
                'notes' => $data['notes'],
                'user_id' => auth()->id(),
            ]);

            $locked->forceFill([
                'sample_revision_count' => $revisionNo,
                'sample_approved_at' => null,
            ])->save();

            $locked->stages()->where('phase', 'sample')->update([
                'status' => 'pending',
                'input_qty' => 1,
                'output_qty' => 0,
                'started_at' => null,
                'finished_at' => null,
            ]);
        });

        return back()->with('success', 'Sampel ditandai perlu revisi. Tahap sampel dibuka kembali.');
    }
```

- [ ] **Step 8: Jalankan tes untuk memastikan lulus**

Run: `php artisan test --filter=ProductionSampleTest`
Expected: PASS

- [ ] **Step 9: Jalankan seluruh suite**

Run: `php artisan test`
Expected: PASS

- [ ] **Step 10: Commit** (konfirmasi dulu ke pemilik repo)

```bash
vendor/bin/pint --dirty
git add database/migrations/2026_09_03_000002_create_production_sample_revisions_table.php app/Models/ProductionSampleRevision.php app/Models/Production.php app/Http/Controllers/Admin/ProductionController.php tests/Feature/Admin/ProductionSampleTest.php
git commit -m "Add sample approval and revision tracking to production batches"
```

---

### Task 3: Penjaga perubahan target 1 pcs ↔ massal

Struktur tahap ditetapkan saat batch dibuat. Mengubah target dari 1 ke 50 akan meninggalkan batch tanpa fase sampel padahal aturannya mewajibkan, jadi perubahan yang melintasi batas itu ditolak.

**Files:**
- Modify: `app/Http/Controllers/Admin/ProductionController.php` (method `update()`)
- Test: `tests/Feature/Admin/ProductionFlowTest.php` (tambah dua tes)

**Interfaces:**
- Consumes: `Production::$planned_qty`, helper `batch()` di `ProductionFlowTest` (Task 1)
- Produces: pesan validasi `planned_qty` untuk perubahan lintas batas

- [ ] **Step 1: Tambah tes (gagal dulu)**

Sisipkan dua method berikut di akhir kelas `tests/Feature/Admin/ProductionFlowTest.php`, sebelum kurung tutup kelas:

```php
    public function test_planned_qty_cannot_cross_from_single_pcs_to_mass(): void
    {
        [$prod] = $this->batch(1);

        $this->actingAs($this->admin())
            ->put(route('admin.productions.update', $prod), [
                'planned_qty' => 50, 'start_date' => now()->toDateString(), 'status' => 'in_progress',
            ])
            ->assertSessionHasErrors('planned_qty');

        $this->assertSame(1, $prod->fresh()->planned_qty);
    }

    public function test_planned_qty_can_still_change_within_the_mass_range(): void
    {
        [$prod] = $this->batch(100);

        $this->actingAs($this->admin())
            ->put(route('admin.productions.update', $prod), [
                'planned_qty' => 150, 'start_date' => now()->toDateString(), 'status' => 'in_progress',
            ])
            ->assertSessionHas('success');

        $this->assertSame(150, $prod->fresh()->planned_qty);
    }
```

- [ ] **Step 2: Jalankan tes untuk memastikan gagal**

Run: `php artisan test --filter=test_planned_qty_cannot_cross_from_single_pcs_to_mass`
Expected: FAIL — `Session is missing expected key: errors`

- [ ] **Step 3: Tambah penjaga di `update()`**

Di `app/Http/Controllers/Admin/ProductionController.php`, sisipkan tepat setelah blok `$data = $request->validate([...]);` di dalam `update()` dan sebelum perhitungan `$minPlanned`:

```php
        // Fase sampel ditentukan saat batch dibuat, jadi target tidak boleh melintasi
        // batas 1 pcs ↔ produksi massal.
        if (((int) $production->planned_qty === 1) !== ((int) $data['planned_qty'] === 1)) {
            return back()->withErrors([
                'planned_qty' => 'Target tidak bisa diubah dari 1 pcs ke produksi massal (atau sebaliknya). Buat batch baru.',
            ])->withInput();
        }
```

- [ ] **Step 4: Jalankan tes untuk memastikan lulus**

Run: `php artisan test --filter=ProductionFlowTest`
Expected: PASS

- [ ] **Step 5: Commit** (konfirmasi dulu ke pemilik repo)

```bash
vendor/bin/pint --dirty
git add app/Http/Controllers/Admin/ProductionController.php tests/Feature/Admin/ProductionFlowTest.php
git commit -m "Block production target changes across the single-piece boundary"
```

---

### Task 4: Tampilan batch berfase

**Files:**
- Modify: `app/Http/Controllers/Admin/ProductionController.php` (method `show()`)
- Modify: `resources/views/admin/productions/show.blade.php`
- Modify: `resources/views/admin/productions/create.blade.php`
- Modify: `resources/views/admin/machine-categories/create.blade.php`
- Modify: `resources/views/admin/machine-categories/edit.blade.php`
- Modify: `resources/views/admin/machine-categories/index.blade.php`
- Test: `tests/Feature/Admin/ProductionSampleTest.php` (tambah satu tes render)

**Interfaces:**
- Consumes: `ProductionStage::stageLabel()`, `ProductionStage::label()`, `ProductionStage::carriesQty()`, `Production::sampleRevisions()`, `Production::hasSamplePhase()`, `Production::sampleApproved()`, `Production::sampleUnits()`, `Production::lockCause()`
- Produces: halaman `admin.productions.show` yang mengelompokkan tahap per fase

- [ ] **Step 1: Tambah tes render (gagal dulu)**

Sisipkan di akhir kelas `tests/Feature/Admin/ProductionSampleTest.php`:

```php
    public function test_batch_page_groups_stages_by_phase_and_lists_revisions(): void
    {
        $prod = $this->batch();
        $this->finishSampleWork($prod);
        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $this->sampleQc($prod)]), [
                'action' => 'revise_sample', 'notes' => 'Panjang lengan kurang 2cm',
            ]);

        $this->actingAs($this->admin())
            ->get(route('admin.productions.show', $prod))
            ->assertOk()
            ->assertSee('Tahap Sampel')
            ->assertSee('Produksi Massal')
            ->assertSee('Pembuatan Pola')
            ->assertSee('QC Sampel')
            ->assertSee('QC &amp; Packing', false)
            ->assertSee('Panjang lengan kurang 2cm')
            ->assertSee('Revisi ke-1');
    }

    public function test_single_pcs_batch_page_explains_there_is_no_sample_phase(): void
    {
        $prod = $this->batch(1);

        $this->actingAs($this->admin())
            ->get(route('admin.productions.show', $prod))
            ->assertOk()
            ->assertSee('produk ini sekaligus sampelnya')
            ->assertDontSee('Tahap Sampel');
    }
```

- [ ] **Step 2: Jalankan tes untuk memastikan gagal**

Run: `php artisan test --filter="test_batch_page_groups_stages_by_phase_and_lists_revisions"`
Expected: FAIL — teks "Tahap Sampel" tidak ditemukan di halaman.

- [ ] **Step 3: Perbarui `show()` di controller**

Ganti method `show()` di `app/Http/Controllers/Admin/ProductionController.php`:

```php
    public function show(Production $production)
    {
        $production->load([
            'product.materials.material',
            'machine',
            'stages' => fn ($q) => $q->orderBy('sort_order'),
            'stages.machine',
            'productionMaterials.material',
            'sampleRevisions.user',
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

        // Estimated material usage: recipe × (target + sampel yang dibuat).
        $units = (int) $production->planned_qty + $production->sampleUnits();

        $bahan = $production->product->materials->map(fn ($line) => [
            'name' => $line->material?->name ?? '—',
            'unit' => $line->material?->unit ?? '',
            'per_unit' => (int) $line->qty_required,
            'est' => (int) $line->qty_required * $units,
        ]);

        return view('admin.productions.show', compact('production', 'machinesByStage', 'bahan'));
    }
```

- [ ] **Step 4: Ganti kartu "Tahap Produksi" di `show.blade.php`**

Di `resources/views/admin/productions/show.blade.php`, ganti seluruh blok `<x-ui.card title="Tahap Produksi" ...>...</x-ui.card>` dengan:

```blade
        @php
            $phaseMeta = [
                'common' => ['Persiapan', 'Desain dan pola dikerjakan sekali di awal'],
                'sample' => ['Tahap Sampel', 'Satu potong dulu — produksi massal terbuka setelah sampel disetujui'],
                'mass' => ['Produksi Massal', 'Tahap berikutnya bisa mulai saat tahap sebelumnya mencapai 50%'],
            ];
            $grouped = $production->stages->sortBy('sort_order')->groupBy('phase');
            $gate = $production->gateQty();
            $locked = $production->status === 'completed';
        @endphp

        @if (! $production->hasSamplePhase())
            <x-ui.card>
                <p class="text-sm text-ink-600">Batch 1 pcs — produk ini sekaligus sampelnya, jadi tidak ada fase sampel terpisah.</p>
            </x-ui.card>
        @endif

        @foreach (['common', 'sample', 'mass'] as $phase)
            @continue (! isset($grouped[$phase]))
            <x-ui.card :title="$phaseMeta[$phase][0]" :subtitle="$phaseMeta[$phase][1]">
                @if ($phase === 'mass' && $production->hasSamplePhase() && ! $production->sampleApproved())
                    <p class="text-xs text-amber-600 mb-3">Terkunci sampai sampel disetujui.</p>
                @endif
                <div class="space-y-3">
                    @foreach ($grouped[$phase] as $stage)
                        @php
                            $unlocked = $production->stageUnlocked($stage);
                            $hasQty = $stage->carriesQty();
                            $qtyEditable = $hasQty && $stage->phase === 'mass';
                            // Fase sampel selalu 1 pcs, jadi progresnya biner — bukan 1% dari target batch.
                            $pct = ($hasQty && $stage->phase === 'mass')
                                ? $production->stageProgressPct($stage)
                                : ($stage->status === 'completed' ? 100 : 0);
                            $machines = $machinesByStage[$stage->stage] ?? [];
                            $maxIn = $production->stageMaxInput($stage);
                            $minOut = $production->stageMinOutput($stage);
                            $isSampleQc = $stage->phase === 'sample' && $stage->stage === 'qc_packing';
                            $badge = match($stage->status) {
                                'completed' => ['bg-emerald-50 border-emerald-200', 'Selesai'],
                                'in_progress' => ['bg-ink-50 border-ink-200', 'Berlangsung'],
                                default => ['bg-white border-ink-100', $unlocked ? 'Siap' : 'Terkunci'],
                            };
                        @endphp
                        <div class="border rounded-xl px-4 py-3 {{ $badge[0] }}">
                            <div class="flex items-center justify-between mb-2">
                                <p class="font-medium text-ink-900">{{ $stage->sort_order }}. {{ $stage->label() }}</p>
                                <span class="text-xs text-ink-500">{{ $badge[1] }}{{ $hasQty ? ' · '.$pct.'%' : '' }}</span>
                            </div>
                            @if ($hasQty)
                                <div class="h-1.5 bg-ink-100 rounded-full overflow-hidden mb-3">
                                    <div class="h-full bg-ink-900" style="width: {{ $pct }}%"></div>
                                </div>
                            @endif

                            @if (! $unlocked && $stage->status === 'pending')
                                <p class="text-xs text-ink-400">
                                    @switch ($production->lockCause($stage))
                                        @case ('sample_not_approved') Menunggu sampel disetujui. @break
                                        @case ('gate_50') Menunggu tahap sebelumnya mencapai {{ $gate }} pcs (50%). @break
                                        @default Menunggu tahap sebelumnya selesai.
                                    @endswitch
                                </p>
                            @else
                                <form action="{{ route('admin.productions.stage', [$production->id, $stage->id]) }}" method="POST" class="grid grid-cols-2 md:grid-cols-4 gap-2 items-end">
                                    @csrf @method('PATCH')
                                    @if ($qtyEditable)
                                        <div class="field !mb-0">
                                            <label class="label text-xs">Input</label>
                                            <input type="number" name="input_qty" min="0" max="{{ $maxIn }}" value="{{ $stage->input_qty }}" class="input" {{ $locked ? 'disabled' : '' }}>
                                            <p class="text-[11px] text-ink-400 mt-0.5">Maks {{ $maxIn }} pcs</p>
                                        </div>
                                        <div class="field !mb-0">
                                            <label class="label text-xs">Output</label>
                                            <input type="number" name="output_qty" min="{{ $minOut }}" value="{{ $stage->output_qty }}" class="input" {{ $locked ? 'disabled' : '' }}>
                                            @if ($minOut > 0)
                                                <p class="text-[11px] text-ink-400 mt-0.5">Min {{ $minOut }} pcs</p>
                                            @endif
                                        </div>
                                    @elseif ($hasQty)
                                        <div class="field !mb-0 md:col-span-2">
                                            <label class="label text-xs">Jumlah</label>
                                            <p class="text-sm text-ink-700 tabular-nums">1 pcs (sampel)</p>
                                        </div>
                                    @else
                                        <div class="field !mb-0 md:col-span-2">
                                            <label class="label text-xs">Jumlah</label>
                                            <p class="text-sm text-ink-500">Tidak menghasilkan pcs</p>
                                        </div>
                                    @endif
                                    <div class="field !mb-0">
                                        <label class="label text-xs">Mesin</label>
                                        <select name="production_machine_id" class="input" {{ $locked ? 'disabled' : '' }}>
                                            <option value="">— Pilih —</option>
                                            @foreach ($machines as $mid => $mname)
                                                <option value="{{ $mid }}" @selected($stage->production_machine_id == $mid)>{{ $mname }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="flex gap-2">
                                        @unless ($locked)
                                            @if ($stage->status === 'pending')
                                                <button name="action" value="start" class="btn-secondary text-xs">Mulai</button>
                                            @elseif ($stage->status === 'in_progress')
                                                <button name="action" value="save" class="btn-secondary text-xs">Simpan</button>
                                                <button name="action" value="finish" class="btn-primary text-xs">Selesai</button>
                                            @else
                                                <button name="action" value="save" class="btn-secondary text-xs">Simpan</button>
                                            @endif
                                        @endunless
                                    </div>
                                </form>

                                @if ($isSampleQc && $stage->status === 'completed' && ! $locked)
                                    <div class="mt-3 border-t border-ink-100 pt-3">
                                        @if ($production->sampleApproved())
                                            <p class="text-xs text-emerald-700">Sampel disetujui {{ $production->sample_approved_at->translatedFormat('d M Y') }}. Produksi massal terbuka.</p>
                                        @else
                                            <form action="{{ route('admin.productions.stage', [$production->id, $stage->id]) }}" method="POST" class="space-y-2">
                                                @csrf @method('PATCH')
                                                <label class="label text-xs">Catatan revisi (wajib kalau sampel perlu diperbaiki)</label>
                                                <textarea name="notes" rows="2" class="input" placeholder="Contoh: panjang lengan kurang 2cm">{{ old('notes') }}</textarea>
                                                @error('notes')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                                                <div class="flex gap-2">
                                                    <button name="action" value="approve_sample" class="btn-primary text-xs">Sampel disetujui</button>
                                                    <button name="action" value="revise_sample" class="btn-secondary text-xs">Perlu revisi</button>
                                                </div>
                                            </form>
                                        @endif
                                    </div>
                                @endif

                                @if (empty($machines) && $hasQty)
                                    <p class="text-xs text-amber-600 mt-1">Belum ada mesin aktif untuk tahap ini. Tambahkan kategori mesin bertahap "{{ $stage->label() }}".</p>
                                @endif
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-ui.card>
        @endforeach

        @if ($production->sampleRevisions->isNotEmpty())
            <x-ui.card title="Riwayat Revisi Sampel" subtitle="{{ $production->sample_revision_count }} kali diperbaiki sebelum disetujui">
                <ul class="space-y-3">
                    @foreach ($production->sampleRevisions as $rev)
                        <li class="border-l-2 border-amber-300 pl-3">
                            <p class="text-sm font-medium text-ink-900">Revisi ke-{{ $rev->revision_no }}
                                <span class="text-xs font-normal text-ink-400">· {{ $rev->created_at?->translatedFormat('d M Y') }}{{ $rev->user ? ' · '.$rev->user->name : '' }}</span>
                            </p>
                            <p class="text-sm text-ink-600">{{ $rev->notes }}</p>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif
```

- [ ] **Step 5: Perbarui subtitle kartu bahan**

Di berkas yang sama, ganti baris subtitle kartu "Bahan Terpakai":

```blade
        <x-ui.card title="Bahan Terpakai" subtitle="{{ $production->status === 'completed' ? 'Aktual (batch selesai)' : 'Estimasi dari resep × (target + sampel)' }}">
```

- [ ] **Step 6: Tambah keterangan sampel di `create.blade.php`**

Di `resources/views/admin/productions/create.blade.php`, sisipkan tepat setelah `</x-ui.card>` yang membungkus "Informasi Produksi":

```blade
    <x-ui.card>
        <p class="text-sm text-ink-600">
            Target lebih dari 1 pcs akan membuat fase sampel: satu potong dikerjakan dan disetujui dulu
            sebelum cutting massal boleh dimulai. Target 1 pcs langsung dikerjakan tanpa fase sampel.
        </p>
    </x-ui.card>
```

- [ ] **Step 7: Perbarui label tahap di view kategori mesin**

Di `resources/views/admin/machine-categories/create.blade.php` dan `edit.blade.php`, ganti baris `@php $stageLabels = [...]; @endphp` dengan:

```blade
@php $stageLabels = collect(\App\Models\ProductionStage::STAGES)->mapWithKeys(fn ($s) => [$s => \App\Models\ProductionStage::stageLabel($s)])->toArray(); @endphp
```

Di `resources/views/admin/machine-categories/index.blade.php`, ganti baris `const labels = {...};` dengan:

```blade
    const labels = @json(collect(\App\Models\ProductionStage::STAGES)->mapWithKeys(fn ($s) => [$s => \App\Models\ProductionStage::stageLabel($s)]));
```

- [ ] **Step 8: Jalankan tes untuk memastikan lulus**

Run: `php artisan test --filter=ProductionSampleTest`
Expected: PASS

- [ ] **Step 9: Jalankan seluruh suite**

Run: `php artisan test`
Expected: PASS

- [ ] **Step 10: Commit** (konfirmasi dulu ke pemilik repo)

```bash
vendor/bin/pint --dirty
git add app/Http/Controllers/Admin/ProductionController.php resources/views/admin/productions resources/views/admin/machine-categories tests/Feature/Admin/ProductionSampleTest.php
git commit -m "Group batch stages by phase and surface sample approval in the UI"
```

---

### Task 5: Seeder dan verifikasi menyeluruh

**Files:**
- Modify: `database/seeders/DatabaseSeeder.php`
- Modify: `CLAUDE.md`

**Interfaces:**
- Consumes: nilai `stage` baru (Task 1)
- Produces: data demo yang konsisten dengan alur berfase

- [ ] **Step 1: Perbarui pemetaan kategori mesin di seeder**

Di `database/seeders/DatabaseSeeder.php`, ganti dua baris kategori QC dan packing (sekitar baris 106–107):

```php
        $catQc = MachineCategory::create(['name' => 'Alat QC & Finishing', 'code' => 'CAT-QC', 'stage' => 'qc_packing']);
        $catPack = MachineCategory::create(['name' => 'Mesin Packing', 'code' => 'CAT-PACK', 'stage' => 'qc_packing']);
```

- [ ] **Step 2: Jalankan seed ulang penuh**

Run: `php artisan migrate:fresh --seed`
Expected: selesai tanpa error, semua migrasi `DONE`.

- [ ] **Step 3: Periksa data hasil seed**

Run: `php artisan tinker --execute="dump(\App\Models\MachineCategory::pluck('stage','code')->toArray());"`
Expected: `['CAT-CUT' => 'cutting', 'CAT-SEW' => 'sewing', 'CAT-QC' => 'qc_packing', 'CAT-PACK' => 'qc_packing']`

- [ ] **Step 4: Pastikan kategori mesin hasil seed memetakan ke tahap yang valid**

Halaman batch sudah ditutup tes render di Task 4, jadi yang perlu diperiksa di sini adalah data seed-nya:
kategori mesin yang menunjuk tahap tak dikenal akan membuat dropdown mesin kosong tanpa error.

Run: `php artisan tinker --execute="dump(App\Models\MachineCategory::whereNotNull('stage')->get()->reject(fn (\$c) => in_array(\$c->stage, App\Models\ProductionStage::STAGES, true))->pluck('stage', 'code')->toArray());"`
Expected: array kosong `[]` — tidak ada kategori yang menunjuk tahap di luar daftar.

Run: `php artisan tinker --execute="dump(App\Models\ProductionMachine::where('status', 'active')->with('category')->get()->groupBy(fn (\$m) => \$m->category?->stage)->map->count()->toArray());"`
Expected: `cutting`, `sewing`, dan `qc_packing` masing-masing punya minimal satu mesin aktif, sehingga
dropdown mesin di ketiga tahap ber-qty tidak kosong.

Pemeriksaan lewat browser tidak dilakukan di sini — itu langkah manual untuk pemilik repo setelah
seluruh rencana selesai.

- [ ] **Step 5: Perbarui dokumentasi arsitektur di `CLAUDE.md`**

Di bagian **Models & relationships**, ganti kalimat tentang `Production`/`ProductionStage` menjadi:

```markdown
- **Inventory / production**: `Material`, `Supplier`, `Purchase` + `PurchaseItem`, `Production` + `ProductionStage` + `ProductionSampleRevision`, `ProductionMachine` (`Production belongsTo ProductionMachine` via nullable `production_machine_id`; `ProductionMachine::STATUSES = ['active','maintenance','inactive']`). `Production::STATUSES = ['planned','in_progress','qc','completed','cancelled']`. Tahapan produksi berfase: `ProductionStage::PHASES = ['common','sample','mass']` dan `ProductionStage::STAGES = ['design','pola','cutting','sewing','qc_packing']`, diurutkan lewat kolom `sort_order`. Batch dengan target lebih dari 1 pcs wajib menyelesaikan fase sampel 1 pcs dan disetujui (`productions.sample_approved_at`) sebelum cutting massal boleh dimulai; batch 1 pcs tidak punya fase sampel. Sampel memakan bahan tapi tidak masuk stok jual. Note: materials are NOT tied to a production batch — they live on the product as a recipe, and creating a production no longer decrements material stock.
```

Di bagian **Seed accounts**, ganti "4 `MachineCategory` rows (cutting/sewing/qc/packing)" menjadi "4 `MachineCategory` rows (cutting/sewing/qc_packing ×2)".

- [ ] **Step 6: Jalankan seluruh suite dan formatter**

Run: `php artisan test`
Expected: PASS, tanpa satu pun tes merah.

Run: `vendor/bin/pint --test --dirty`
Expected: tidak ada berkas yang perlu diformat. Kalau ada, jalankan `vendor/bin/pint --dirty` lalu ulangi.

**Jangan pernah menjalankan `vendor/bin/pint` tanpa `--dirty` di repo ini.** Ada sekitar 43 berkas
lama yang memang belum pernah diformat (`app/Models/*.php`, `app/Services/FuzzyMamdaniService.php`,
migrasi-migrasi 2024, `routes/web.php`, dan lain-lain). Memformat semuanya akan menghasilkan diff
besar yang tidak ada hubungannya dengan pekerjaan ini dan mengubur perubahan yang sebenarnya.

- [ ] **Step 7: Commit** (konfirmasi dulu ke pemilik repo)

```bash
git add database/seeders/DatabaseSeeder.php CLAUDE.md
git commit -m "Map machine categories to the merged QC and packing stage"
```
