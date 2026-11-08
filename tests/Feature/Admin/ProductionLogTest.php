<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\Production;
use App\Models\ProductionLog;
use App\Models\ProductionStage;
use App\Models\ProductSize;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductionLogTest extends TestCase
{
    use RefreshDatabase;

    private ?User $user = null;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        return $this->user ??= User::create([
            'name' => 'Operator Satu', 'email' => 'op@labasa.test', 'password' => 'password', 'role' => User::ROLE_ADMIN,
        ]);
    }

    private function batch(): Production
    {
        $product = Product::create([
            'category_id' => Category::create(['name' => 'Gamis'])->id,
            'name' => 'Gamis A', 'sku' => 'GA-1', 'price' => 100000, 'stock' => 0, 'is_active' => true,
        ]);
        ProductSize::create(['product_id' => $product->id, 'size' => 'M', 'stock' => 0]);

        $this->actingAs($this->admin())->post(route('admin.productions.store'), [
            'product_id' => $product->id,
            'start_date' => now()->toDateString(),
            'sizes' => ['M' => 50],
        ])->assertSessionHasNoErrors();

        return Production::latest('id')->first();
    }

    private function stage(Production $p, string $phase, string $stage): ProductionStage
    {
        return $p->stages()->where('phase', $phase)->where('stage', $stage)->firstOrFail();
    }

    private function pause(Production $p, array $data = [])
    {
        return $this->actingAs($this->admin())->post(route('admin.productions.pause', $p), $data);
    }

    public function test_pause_records_the_note_time_and_user(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-09 07:32:00', 'UTC'));
        $prod = $this->batch();

        $this->pause($prod, ['keterangan' => 'Mesin jahit diperbaiki'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Catatan pause berhasil disimpan.');

        $log = $prod->logs()->sole();
        $this->assertSame('Mesin jahit diperbaiki', $log->keterangan);
        $this->assertSame($this->admin()->id, $log->user_id);
        $this->assertTrue($log->waktu->equalTo(Carbon::parse('2026-11-09 07:32:00', 'UTC')));
    }

    public function test_blank_note_snapshots_the_in_progress_stage(): void
    {
        $prod = $this->batch();
        $this->stage($prod, 'mass', 'sewing')->update(['status' => 'in_progress', 'input_qty' => 30, 'output_qty' => 20]);

        $this->pause($prod, ['keterangan' => '   '])->assertSessionHasNoErrors();

        $this->assertSame('Tahap Sewing Massal: 20 dari 50 pcs', $prod->logs()->sole()->keterangan);
    }

    public function test_snapshot_mentions_every_stage_running_in_parallel(): void
    {
        $prod = $this->batch();
        $this->stage($prod, 'mass', 'cutting')->update(['status' => 'in_progress', 'input_qty' => 20, 'output_qty' => 30]);
        $this->stage($prod, 'mass', 'sewing')->update(['status' => 'in_progress', 'input_qty' => 20, 'output_qty' => 10]);

        $this->pause($prod)->assertSessionHasNoErrors();

        $this->assertSame(
            'Tahap Cutting Massal: 30 dari 50 pcs; Tahap Sewing Massal: 10 dari 30 pcs',
            $prod->logs()->sole()->keterangan
        );
    }

    public function test_snapshot_falls_back_to_the_next_pending_stage(): void
    {
        $prod = $this->batch();

        $this->pause($prod);
        $this->assertSame('Tahap Desain: belum dimulai', $prod->logs()->sole()->keterangan);

        $prod->stages()->where('phase', 'common')->update(['status' => 'completed']);
        $this->pause($prod);
        $this->assertSame('Tahap Cutting Sampel: 0 dari 1 pcs (belum dimulai)', $prod->logs()->pluck('keterangan')->last());
    }

    public function test_note_is_limited_to_500_characters(): void
    {
        $prod = $this->batch();

        $this->pause($prod, ['keterangan' => str_repeat('a', 501)])->assertSessionHasErrors('keterangan');

        $this->assertSame(0, ProductionLog::count());
    }

    #[DataProvider('closedStatuses')]
    public function test_pause_is_rejected_when_the_batch_is_closed(string $status): void
    {
        $prod = $this->batch();
        $prod->forceFill(['status' => $status])->save();

        $this->pause($prod, ['keterangan' => 'Terlambat'])->assertSessionHas('error');

        $this->assertSame(0, ProductionLog::count());
        $this->actingAs($this->admin())->get(route('admin.productions.show', $prod))
            ->assertOk()->assertDontSee(route('admin.productions.pause', $prod), false);
    }

    public static function closedStatuses(): array
    {
        return [['completed'], ['cancelled']];
    }

    public function test_pause_changes_neither_the_batch_nor_its_stages(): void
    {
        $prod = $this->batch();
        $design = $this->stage($prod, 'common', 'design');
        $design->update(['status' => 'in_progress']);
        $prod->forceFill(['status' => 'in_progress'])->save();

        $this->pause($prod, ['keterangan' => 'Istirahat'])->assertSessionHasNoErrors();

        $this->assertSame('in_progress', $prod->fresh()->status);
        $this->assertSame('in_progress', $design->fresh()->status);

        // Tahap tetap bisa dilanjutkan setelah pause.
        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $this->stage($prod, 'common', 'pola')]), ['action' => 'save'])
            ->assertSessionHasNoErrors();
    }

    public function test_logs_are_listed_oldest_first_on_the_show_page(): void
    {
        $prod = $this->batch();

        Carbon::setTestNow(Carbon::parse('2026-11-09 03:00:00', 'UTC'));
        $this->pause($prod, ['keterangan' => 'Catatan pertama']);
        Carbon::setTestNow(Carbon::parse('2026-11-09 05:00:00', 'UTC'));
        $this->pause($prod, ['keterangan' => 'Catatan kedua']);

        // Tersimpan belakangan, tapi waktunya paling awal.
        $prod->logs()->create(['waktu' => Carbon::parse('2026-11-09 01:00:00', 'UTC'), 'keterangan' => 'Catatan terlama']);

        $this->assertSame(
            ['Catatan terlama', 'Catatan pertama', 'Catatan kedua'],
            $prod->logs()->pluck('keterangan')->all()
        );

        $this->actingAs($this->admin())->get(route('admin.productions.show', $prod))
            ->assertOk()
            ->assertSeeInOrder(['Log Produksi', 'Catatan terlama', 'Catatan pertama', 'Catatan kedua', 'Operator Satu']);
    }

    public function test_log_time_is_shown_as_a_full_indonesian_date_in_wib(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-09 07:32:00', 'UTC'));
        $prod = $this->batch();

        $this->pause($prod, ['keterangan' => 'Cek waktu']);

        $this->assertSame('Senin, 9 November 2026 14:32', $prod->logs()->sole()->waktu_label);
        $this->actingAs($this->admin())->get(route('admin.productions.show', $prod))
            ->assertOk()->assertSee('Senin, 9 November 2026 14:32');
    }

    public function test_show_page_has_an_empty_state_and_a_pause_form(): void
    {
        $prod = $this->batch();

        $this->actingAs($this->admin())->get(route('admin.productions.show', $prod))
            ->assertOk()
            ->assertSee('Belum ada catatan pause.')
            ->assertSee(route('admin.productions.pause', $prod), false)
            ->assertSee('name="keterangan"', false);
    }
}
