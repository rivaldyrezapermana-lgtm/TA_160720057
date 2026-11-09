<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\Production;
use App\Models\ProductionLog;
use App\Models\ProductionStage;
use App\Models\ProductionStageSize;
use App\Models\ProductSize;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionDailyLogTest extends TestCase
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
            'name' => 'Operator', 'email' => 'op@labasa.test', 'password' => 'password', 'role' => User::ROLE_ADMIN,
        ]);
    }

    /** Batch dengan fase sampel beres dan cutting massal sedang berjalan. */
    private function sizedBatch(array $sizes = ['M' => 50, 'L' => 30]): Production
    {
        $product = Product::create([
            'category_id' => Category::create(['name' => 'Gamis'])->id,
            'name' => 'Gamis A', 'sku' => 'GA-1', 'price' => 100000, 'stock' => 0, 'is_active' => true,
        ]);
        foreach (array_keys($sizes) as $size) {
            ProductSize::create(['product_id' => $product->id, 'size' => $size, 'stock' => 0]);
        }

        $this->actingAs($this->admin())->post(route('admin.productions.store'), [
            'product_id' => $product->id,
            'start_date' => now()->toDateString(),
            'sizes' => $sizes,
        ])->assertSessionHasNoErrors();

        $prod = Production::latest('id')->first();
        $prod->stages()->where('phase', '!=', 'mass')->update(['status' => 'completed', 'output_qty' => 1]);
        $prod->forceFill(['sample_approved_at' => now(), 'status' => 'in_progress'])->save();
        $this->stage($prod, 'cutting')->update(['status' => 'in_progress']);

        return $prod;
    }

    /** Batch tanpa rincian per ukuran di tahap, sisa cutting 80 pcs. */
    private function unsizedBatch(): Production
    {
        $prod = $this->sizedBatch();
        ProductionStageSize::whereIn('production_stage_id', $prod->stages()->pluck('id'))->delete();

        return $prod;
    }

    private function stage(Production $p, string $stage = 'cutting'): ProductionStage
    {
        return $p->stages()->where('phase', 'mass')->where('stage', $stage)->firstOrFail();
    }

    private function saveStage(Production $p, array $data, string $stage = 'cutting')
    {
        return $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$p, $this->stage($p, $stage)]), $data);
    }

    private function showPage(Production $p)
    {
        return $this->actingAs($this->admin())->get(route('admin.productions.show', $p))->assertOk();
    }

    public function test_sized_save_logs_remaining_before_output_and_remaining_after(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-09 07:00:00', 'UTC'));
        $prod = $this->sizedBatch();

        $this->saveStage($prod, ['action' => 'save', 'size_qty' => ['M' => 30, 'L' => 20]])->assertSessionHasNoErrors();

        $log = ProductionLog::sole();
        $this->assertSame($prod->id, $log->produksi_id);
        $this->assertSame($this->stage($prod)->id, $log->production_stage_id);
        $this->assertSame($this->admin()->id, $log->user_id);
        $this->assertTrue($log->waktu->equalTo(now()));
        $this->assertSame(80, $log->input_qty);
        $this->assertSame(50, $log->output_qty);
        $this->assertSame(30, $log->sisa_qty);
        $this->assertSame('M 30, L 20', $log->keterangan);
    }

    public function test_size_breakdown_leaves_out_sizes_with_no_pcs(): void
    {
        $prod = $this->sizedBatch();

        $this->saveStage($prod, ['action' => 'save', 'size_qty' => ['M' => 10, 'L' => 0]])->assertSessionHasNoErrors();

        $this->assertSame('M 10', ProductionLog::sole()->keterangan);
    }

    public function test_unsized_save_logs_quantities_without_a_breakdown(): void
    {
        $prod = $this->unsizedBatch();

        $this->saveStage($prod, ['action' => 'save', 'qty' => 25])->assertSessionHasNoErrors();

        $log = ProductionLog::sole();
        $this->assertSame(80, $log->input_qty);
        $this->assertSame(25, $log->output_qty);
        $this->assertSame(55, $log->sisa_qty);
        $this->assertNull($log->keterangan);
    }

    public function test_finish_with_pcs_also_logs(): void
    {
        $prod = $this->sizedBatch();

        $this->saveStage($prod, ['action' => 'finish', 'size_qty' => ['M' => 50, 'L' => 30]])->assertSessionHasNoErrors();

        $log = ProductionLog::sole();
        $this->assertSame([80, 80, 0], [$log->input_qty, $log->output_qty, $log->sisa_qty]);
        $this->assertSame('completed', $this->stage($prod)->status);
    }

    public function test_no_log_when_no_pcs_are_recorded(): void
    {
        $sized = $this->sizedBatch();
        $this->saveStage($sized, ['action' => 'save', 'size_qty' => ['M' => 0, 'L' => '']])->assertSessionHasNoErrors();
        $this->saveStage($sized, ['action' => 'save'])->assertSessionHasNoErrors();
        $this->saveStage($sized, ['action' => 'finish'])->assertSessionHasNoErrors();

        $this->assertSame(0, ProductionLog::count());
    }

    public function test_unsized_stage_with_zero_pcs_has_no_log(): void
    {
        $prod = $this->unsizedBatch();

        $this->saveStage($prod, ['action' => 'save', 'qty' => 0])->assertSessionHasNoErrors();
        $this->saveStage($prod, ['action' => 'save'])->assertSessionHasNoErrors();

        $this->assertSame(0, ProductionLog::count());
    }

    public function test_rejected_quantity_writes_no_log(): void
    {
        $prod = $this->sizedBatch();

        $this->saveStage($prod, ['action' => 'save', 'size_qty' => ['M' => 999]])->assertSessionHasErrors('size_qty');

        $this->assertSame(0, ProductionLog::count());
    }

    public function test_sample_stage_never_logs(): void
    {
        $prod = $this->sizedBatch();
        $sample = $prod->stages()->where('phase', 'sample')->where('stage', 'cutting')->firstOrFail();
        $sample->update(['status' => 'in_progress']);

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $sample]), ['action' => 'finish'])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, ProductionLog::count());
    }

    public function test_several_saves_accumulate_in_the_summary_and_reconcile(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-09 02:00:00', 'UTC'));
        $prod = $this->sizedBatch();
        $this->saveStage($prod, ['action' => 'save', 'size_qty' => ['M' => 30, 'L' => 10]]);

        Carbon::setTestNow(Carbon::parse('2026-11-09 05:00:00', 'UTC'));
        $this->saveStage($prod, ['action' => 'save', 'size_qty' => ['M' => 10]]);

        $this->assertSame(
            [['stage' => 'Cutting', 'input' => 80, 'output' => 50, 'sisa' => 30]],
            $prod->fresh()->todayLogSummary()
        );
    }

    public function test_row_reconciles_when_the_stage_receives_more_during_the_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-09 02:00:00', 'UTC'));
        $prod = $this->sizedBatch();

        $this->saveStage($prod, ['action' => 'save', 'size_qty' => ['M' => 40]]);
        $this->stage($prod, 'sewing')->update(['status' => 'in_progress']);
        $this->saveStage($prod, ['action' => 'save', 'size_qty' => ['M' => 10]], 'sewing');
        // Sisa sewing 30, lalu menerima 10 lagi dari cutting: sisa 40 sebelum disimpan lagi.
        $this->saveStage($prod, ['action' => 'save', 'size_qty' => ['L' => 10]]);
        $this->saveStage($prod, ['action' => 'save', 'size_qty' => ['M' => 5, 'L' => 5]], 'sewing');

        $summary = collect($prod->fresh()->todayLogSummary())->keyBy('stage');

        $this->assertSame(['Cutting', 'Sewing'], $summary->keys()->all());
        $this->assertSame(['stage' => 'Cutting', 'input' => 80, 'output' => 50, 'sisa' => 30], $summary['Cutting']);
        $this->assertSame(['stage' => 'Sewing', 'input' => 50, 'output' => 20, 'sisa' => 30], $summary['Sewing']);
    }

    public function test_summary_uses_the_wib_calendar_day(): void
    {
        // 16:59 UTC = 9 Nov 23:59 WIB (kemarin); 17:30 UTC = 10 Nov 00:30 WIB (hari ini).
        Carbon::setTestNow(Carbon::parse('2026-11-09 16:59:00', 'UTC'));
        $prod = $this->sizedBatch();
        $this->saveStage($prod, ['action' => 'save', 'size_qty' => ['M' => 20]]);

        Carbon::setTestNow(Carbon::parse('2026-11-09 17:30:00', 'UTC'));
        $this->saveStage($prod, ['action' => 'save', 'size_qty' => ['M' => 5]]);

        $this->assertSame(
            [['stage' => 'Cutting', 'input' => 60, 'output' => 5, 'sisa' => 55]],
            $prod->fresh()->todayLogSummary()
        );
    }

    public function test_entry_from_early_wib_morning_is_still_today_at_end_of_the_wib_day(): void
    {
        // 18:00 UTC tanggal 9 = 10 Nov 01:00 WIB; 16:00 UTC tanggal 10 = 10 Nov 23:00 WIB.
        Carbon::setTestNow(Carbon::parse('2026-11-09 18:00:00', 'UTC'));
        $prod = $this->sizedBatch();
        $this->saveStage($prod, ['action' => 'save', 'size_qty' => ['M' => 5]]);

        Carbon::setTestNow(Carbon::parse('2026-11-10 16:00:00', 'UTC'));
        $this->assertSame(5, $prod->fresh()->todayLogSummary()[0]['output']);

        Carbon::setTestNow(Carbon::parse('2026-11-10 17:00:00', 'UTC'));
        $this->assertSame([], $prod->fresh()->todayLogSummary());
    }

    public function test_show_page_lists_today_card_and_quantities_in_the_log(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-09 07:00:00', 'UTC'));
        $prod = $this->sizedBatch();
        $this->saveStage($prod, ['action' => 'save', 'size_qty' => ['M' => 30, 'L' => 20]]);

        $this->showPage($prod)
            ->assertSeeInOrder(['Log Hari Ini', 'Senin, 9 November 2026', 'Tahap', 'Input', 'Output', 'Sisa', 'Cutting', '80 pcs', '50 pcs', '30 pcs'])
            ->assertSeeInOrder(['Log Produksi', 'Senin, 9 November 2026 14:00', 'Cutting', 'Input 80', 'Output 50', 'Sisa 30', 'M 30, L 20', 'Operator'])
            ->assertDontSee('Belum ada hasil produksi yang dicatat hari ini.');
    }

    public function test_today_card_has_an_empty_state(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-09 16:59:00', 'UTC'));
        $prod = $this->sizedBatch();
        $this->saveStage($prod, ['action' => 'save', 'size_qty' => ['M' => 20]]);

        Carbon::setTestNow(Carbon::parse('2026-11-09 17:30:00', 'UTC'));

        // Kemarin tetap ada di Log Produksi, tapi tidak di Log Hari Ini.
        $this->showPage($prod)
            ->assertSee('Belum ada hasil produksi yang dicatat hari ini.')
            ->assertSee('Input 80');
    }

    private function inputTag(string $html, string $name): string
    {
        preg_match('/<input[^>]*name="'.preg_quote($name, '/').'"[^>]*>/s', $html, $match);

        return str_replace('disabled:', '', $match[0]);
    }

    /** @return array<string, bool> teks tombol => nonaktif */
    private function stageButtons(string $html, int $stageId): array
    {
        preg_match_all('/<button[^>]*>[^<]*<\/button>/s', $this->stageForm($html, $stageId), $matches);

        $buttons = [];
        foreach ($matches[0] as $button) {
            $tag = str_replace('disabled:', '', $button);
            $buttons[trim(strip_tags($button))] = (bool) preg_match('/\sdisabled(\s|>|=)/', $tag);
        }

        return $buttons;
    }

    public function test_running_stage_inputs_are_open_and_saving_asks_for_confirmation(): void
    {
        $prod = $this->sizedBatch();

        $html = $this->showPage($prod)
            ->assertSee('id="modal-konfirmasi-harian"', false)
            ->assertSee('Yakin menyelesaikan', false)
            ->assertSee('data-stage-label="Cutting"', false)
            ->getContent();

        $this->assertDoesNotMatchRegularExpression('/\bdisabled\b/', $this->inputTag($html, 'size_qty[M]'));
        $this->assertSame(
            ['Selesai Hari Ini' => false, 'Simpan' => false, 'Selesai' => false],
            $this->stageButtons($html, $this->stage($prod)->id),
        );
    }

    public function test_selesai_hari_ini_records_the_pcs_like_a_save(): void
    {
        $prod = $this->sizedBatch();

        $html = $this->showPage($prod)->getContent();
        $this->assertMatchesRegularExpression('/<button name="action" value="save" data-daily-today/', $html);

        $this->saveStage($prod, ['action' => 'save', 'size_qty' => ['M' => 5]])->assertSessionHasNoErrors();

        $this->assertSame(5, (int) $this->stage($prod)->output_qty);
        $this->assertSame('in_progress', $this->stage($prod)->status);
    }

    public function test_unsized_running_stage_input_is_open(): void
    {
        $html = $this->showPage($this->unsizedBatch())->getContent();

        $this->assertDoesNotMatchRegularExpression('/\bdisabled\b/', $this->inputTag($html, 'qty'));
    }

    public function test_pending_stage_input_is_open_because_it_is_not_finished(): void
    {
        $prod = $this->sizedBatch();
        $this->stage($prod)->update(['status' => 'pending']);

        $html = $this->showPage($prod)->getContent();

        $this->assertDoesNotMatchRegularExpression('/\bdisabled\b/', $this->inputTag($html, 'size_qty[M]'));
    }

    public function test_every_save_subtracts_until_the_stage_is_finished_then_inputs_lock(): void
    {
        $prod = $this->sizedBatch();

        $this->saveStage($prod, ['action' => 'save', 'size_qty' => ['M' => 30, 'L' => 10]])->assertSessionHasNoErrors();
        $this->saveStage($prod, ['action' => 'save', 'size_qty' => ['M' => 20, 'L' => 20]])->assertSessionHasNoErrors();

        $stage = $this->stage($prod)->fresh();
        $this->assertSame(0, (int) $stage->input_qty);
        $this->assertSame(80, (int) $stage->output_qty);

        $html = $this->showPage($prod)->getContent();
        $this->assertDoesNotMatchRegularExpression('/\bdisabled\b/', $this->inputTag($html, 'size_qty[M]'));

        $this->saveStage($prod, ['action' => 'finish'])->assertSessionHasNoErrors();

        $html = $this->showPage($prod)->getContent();
        $this->assertMatchesRegularExpression('/\bdisabled\b/', $this->inputTag($html, 'size_qty[M]'));

        // Tombol tetap terlihat, hanya nonaktif.
        $this->assertSame(
            ['Selesai Hari Ini' => true, 'Simpan' => true, 'Selesai' => true],
            $this->stageButtons($html, $stage->id),
        );
    }

    public function test_finished_stage_with_pcs_still_remaining_stays_open_with_a_notice(): void
    {
        $prod = $this->sizedBatch();

        $this->saveStage($prod, ['action' => 'finish', 'size_qty' => ['M' => 10]])->assertSessionHasNoErrors();

        $html = $this->showPage($prod)->assertSee('Isian terkunci setelah sisanya habis.')->getContent();
        $this->assertDoesNotMatchRegularExpression('/\bdisabled\b/', $this->inputTag($html, 'size_qty[M]'));
        $this->assertSame(
            ['Selesai Hari Ini' => false, 'Simpan' => false, 'Selesai' => true],
            $this->stageButtons($html, $this->stage($prod)->id),
        );

        // Pcs yang masih tersisa tetap dapat dikerjakan sampai habis, lalu isiannya terkunci.
        $this->saveStage($prod, ['action' => 'save', 'size_qty' => ['M' => 40, 'L' => 30]])->assertSessionHasNoErrors();

        $html = $this->showPage($prod)->assertDontSee('Isian terkunci setelah sisanya habis.')->getContent();
        $this->assertMatchesRegularExpression('/\bdisabled\b/', $this->inputTag($html, 'size_qty[M]'));
    }

    private function stageForm(string $html, int $stageId): string
    {
        preg_match('/<form[^>]*\/stage\/'.$stageId.'"[^>]*>.*?<\/form>/s', $html, $match);

        return $match[0] ?? '';
    }

    public function test_validation_error_reopens_inputs_with_the_old_values(): void
    {
        $prod = $this->sizedBatch();
        $stage = $this->stage($prod);

        $this->actingAs($this->admin())
            ->from(route('admin.productions.show', $prod))
            ->patch(route('admin.productions.stage', [$prod, $stage]), [
                'action' => 'save', '_stage' => $stage->id, 'size_qty' => ['M' => 999],
            ])
            ->assertSessionHasErrors('size_qty');

        $html = $this->get(route('admin.productions.show', $prod))->assertOk()->getContent();

        preg_match('/<input[^>]*name="size_qty\[M\]"[^>]*>/s', $html, $match);
        $this->assertStringContainsString('value="999"', $match[0]);
        $this->assertDoesNotMatchRegularExpression('/\bdisabled\b/', str_replace('disabled:', '', $match[0]));
    }

    public function test_completed_batch_keeps_its_buttons_disabled_and_has_no_popup(): void
    {
        $prod = $this->sizedBatch();
        $prod->forceFill(['status' => 'completed'])->save();

        $html = $this->showPage($prod)
            ->assertDontSee('id="modal-konfirmasi-harian"', false)
            ->getContent();

        $this->assertSame(
            ['Selesai Hari Ini' => true, 'Simpan' => true, 'Selesai' => true],
            $this->stageButtons($html, $this->stage($prod)->id),
        );
    }

    public function test_pause_still_has_null_quantities_and_is_left_out_of_the_summary(): void
    {
        $prod = $this->sizedBatch();

        $this->actingAs($this->admin())
            ->post(route('admin.productions.pause', $prod), ['keterangan' => 'Istirahat'])
            ->assertSessionHasNoErrors();

        $log = ProductionLog::sole();
        $this->assertNull($log->production_stage_id);
        $this->assertNull($log->input_qty);
        $this->assertNull($log->output_qty);
        $this->assertNull($log->sisa_qty);
        $this->assertSame([], $prod->fresh()->todayLogSummary());

        $this->showPage($prod)
            ->assertSee('Istirahat')
            ->assertDontSee('Input 0')
            ->assertSee('Belum ada hasil produksi yang dicatat hari ini.');
    }
}
