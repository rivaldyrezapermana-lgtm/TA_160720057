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
        $prod = $this->sizedBatch();
        $this->saveStage($prod, ['action' => 'save', 'size_qty' => ['M' => 30, 'L' => 10]]);
        $this->saveStage($prod, ['action' => 'save', 'size_qty' => ['M' => 10]]);

        $summary = $prod->fresh()->openDaySummary();

        $this->assertSame(
            [['stage' => 'Cutting', 'input' => 80, 'output' => 50, 'sisa' => 30, 'sizes' => ['M' => 40, 'L' => 10]]],
            $summary['stages']
        );
        $this->assertSame(50, $summary['total']);
        $this->assertSame(['M' => 40, 'L' => 10], $summary['sizes']);
    }

    public function test_row_reconciles_when_the_stage_receives_more_during_the_day(): void
    {
        $prod = $this->sizedBatch();

        $this->saveStage($prod, ['action' => 'save', 'size_qty' => ['M' => 40]]);
        $this->stage($prod, 'sewing')->update(['status' => 'in_progress']);
        $this->saveStage($prod, ['action' => 'save', 'size_qty' => ['M' => 10]], 'sewing');
        // Sisa sewing 30, lalu menerima 10 lagi dari cutting: sisa 40 sebelum disimpan lagi.
        $this->saveStage($prod, ['action' => 'save', 'size_qty' => ['L' => 10]]);
        $this->saveStage($prod, ['action' => 'save', 'size_qty' => ['M' => 5, 'L' => 5]], 'sewing');

        $rows = collect($prod->fresh()->openDaySummary()['stages'])->keyBy('stage');

        $this->assertSame(['Cutting', 'Sewing'], $rows->keys()->all());
        $this->assertSame([80, 50, 30], [$rows['Cutting']['input'], $rows['Cutting']['output'], $rows['Cutting']['sisa']]);
        $this->assertSame([50, 20, 30], [$rows['Sewing']['input'], $rows['Sewing']['output'], $rows['Sewing']['sisa']]);
        $this->assertSame(['M' => 15, 'L' => 5], $rows['Sewing']['sizes']);
    }

    public function test_today_card_shows_saved_pcs_per_stage_and_size(): void
    {
        $prod = $this->sizedBatch();
        $this->saveStage($prod, ['action' => 'save', 'size_qty' => ['M' => 30, 'L' => 20]]);

        $this->showPage($prod)
            ->assertSeeInOrder(['Log Hari Ini', 'Belum dimulai', 'Tahap', 'Input', 'Output', 'Sisa', 'Rincian Ukuran', 'Cutting', '80 pcs', '50 pcs', '30 pcs', 'M 30, L 20'])
            ->assertSee('Total output:')
            ->assertDontSee('Belum ada pcs yang disimpan hari ini.');
    }

    public function test_today_card_has_an_empty_state(): void
    {
        $this->showPage($this->sizedBatch())
            ->assertSee('Belum ada pcs yang disimpan hari ini.')
            ->assertSee('Belum dimulai');
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

    public function test_running_stage_inputs_are_open_and_simpan_saves_without_a_popup(): void
    {
        $prod = $this->sizedBatch();

        $html = $this->showPage($prod)->getContent();

        $this->assertDoesNotMatchRegularExpression('/\bdisabled\b/', $this->inputTag($html, 'size_qty[M]'));
        $this->assertSame(['Simpan' => false, 'Selesai' => false], $this->stageButtons($html, $this->stage($prod)->id));
        $this->assertStringNotContainsString('data-daily-form', $html);
    }

    public function test_day_flow_mulai_simpan_then_selesai_hari_ini_writes_one_summary(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-18 01:00:00', 'UTC'));
        $prod = $this->sizedBatch();
        $prod->forceFill(['status' => 'planned'])->save();

        $this->actingAs($this->admin())->post(route('admin.productions.day.start', $prod))->assertSessionHas('success');
        $this->assertSame('in_progress', $prod->fresh()->status);
        $this->showPage($prod)->assertSee('Berjalan')->assertSee('sejak pukul 08:00');

        $this->saveStage($prod, ['action' => 'save', 'size_qty' => ['M' => 30, 'L' => 20]]);
        $this->stage($prod, 'sewing')->update(['status' => 'in_progress']);
        $this->saveStage($prod, ['action' => 'save', 'size_qty' => ['M' => 10, 'L' => 5]], 'sewing');

        Carbon::setTestNow(Carbon::parse('2026-11-18 10:00:00', 'UTC'));
        $this->actingAs($this->admin())->post(route('admin.productions.day.finish', $prod))->assertSessionHas('success');

        $log = ProductionLog::where('jenis', ProductionLog::SELESAI_HARI)->sole();
        $this->assertSame('Selesai mengerjakan Cutting dan Sewing, total 65 pcs: M 40 pcs, L 25 pcs.', $log->keterangan);
        $this->assertSame(65, $log->output_qty);
        $this->assertSame(['Cutting', 'Sewing'], array_column($log->rincian['stages'], 'stage'));

        $html = $this->showPage($prod)
            ->assertSee('Selesai')
            ->assertSee('pukul 17:00')
            ->assertSee('Belum ada pcs yang disimpan hari ini.')
            ->assertSeeInOrder(['Log Produksi', 'Mulai', 'Produksi hari ini dimulai.', 'Selesai Hari Ini', 'Selesai mengerjakan Cutting dan Sewing', 'Cutting: 50 pcs (M 30, L 20)', 'Sewing: 15 pcs (M 10, L 5)'])
            ->getContent();

        // Hasil Simpan tidak tampil satu per satu di Log Produksi.
        $this->assertStringNotContainsString('Input 80', $html);
    }

    public function test_unsized_running_stage_input_is_open(): void
    {
        $html = $this->showPage($this->unsizedBatch())->getContent();

        $this->assertDoesNotMatchRegularExpression('/\bdisabled\b/', $this->inputTag($html, 'qty'));
    }

    public function test_stage_stays_disabled_until_mulai_is_clicked(): void
    {
        $prod = $this->sizedBatch();
        $stage = $this->stage($prod);
        $stage->update(['status' => 'pending']);

        $html = $this->showPage($prod)->assertSee('Tahap ini belum dimulai. Klik Mulai untuk membuka isian.')->getContent();

        $this->assertMatchesRegularExpression('/\bdisabled\b/', $this->inputTag($html, 'size_qty[M]'));
        $this->assertSame(['Mulai' => false, 'Simpan' => true, 'Selesai' => true], $this->stageButtons($html, $stage->id));

        $this->saveStage($prod, ['action' => 'start'])->assertSessionHasNoErrors();

        $html = $this->showPage($prod)->assertDontSee('Tahap ini belum dimulai.')->getContent();
        $this->assertDoesNotMatchRegularExpression('/\bdisabled\b/', $this->inputTag($html, 'size_qty[M]'));
        $this->assertSame(['Simpan' => false, 'Selesai' => false], $this->stageButtons($html, $stage->id));
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
            ['Simpan' => true, 'Selesai' => true],
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
            ['Simpan' => false, 'Selesai' => true],
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

    public function test_completed_batch_keeps_its_buttons_disabled(): void
    {
        $prod = $this->sizedBatch();
        $prod->forceFill(['status' => 'completed'])->save();

        $html = $this->showPage($prod)->getContent();

        $this->assertSame(['Simpan' => true, 'Selesai' => true], $this->stageButtons($html, $this->stage($prod)->id));
        $this->assertMatchesRegularExpression('/<button[^>]*\sdisabled(?=[\s>])[^>]*>Mulai<\/button>/', $html);
    }

    public function test_pause_still_has_null_quantities_and_is_left_out_of_the_summary(): void
    {
        $prod = $this->sizedBatch();

        $this->actingAs($this->admin())
            ->post(route('admin.productions.pause', $prod), ['keterangan' => 'Istirahat'])
            ->assertSessionHasNoErrors();

        $log = ProductionLog::sole();
        $this->assertSame(ProductionLog::PAUSE, $log->jenis);
        $this->assertNull($log->production_stage_id);
        $this->assertNull($log->output_qty);
        $this->assertSame([], $prod->fresh()->openDaySummary()['stages']);

        $this->showPage($prod)
            ->assertSee('Istirahat')
            ->assertSee('Belum ada pcs yang disimpan hari ini.');
    }

    public function test_selesai_hari_ini_needs_mulai_first(): void
    {
        $prod = $this->sizedBatch();
        $this->saveStage($prod, ['action' => 'save', 'size_qty' => ['M' => 5]]);

        $this->actingAs($this->admin())->post(route('admin.productions.day.finish', $prod))->assertSessionHas('error');

        $this->assertSame(0, ProductionLog::where('jenis', ProductionLog::SELESAI_HARI)->count());
        $this->assertMatchesRegularExpression('/id="btn-selesai-hari"[^>]*\sdisabled(?=[\s>])/', $this->showPage($prod)->getContent());
    }

    public function test_mulai_cannot_be_pressed_twice_and_is_disabled_while_running(): void
    {
        $prod = $this->sizedBatch();

        $this->actingAs($this->admin())->post(route('admin.productions.day.start', $prod))->assertSessionHas('success');
        $this->actingAs($this->admin())->post(route('admin.productions.day.start', $prod))->assertSessionHas('error');

        $this->assertSame(1, ProductionLog::where('jenis', ProductionLog::MULAI)->count());
        $html = $this->showPage($prod)->getContent();
        $this->assertMatchesRegularExpression('/<button[^>]*\sdisabled(?=[\s>])[^>]*>Mulai<\/button>/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="btn-selesai-hari"[^>]*\sdisabled(?=[\s>])/', $html);
    }

    public function test_pcs_saved_before_mulai_are_included_in_the_next_summary(): void
    {
        $prod = $this->sizedBatch();
        $this->saveStage($prod, ['action' => 'save', 'size_qty' => ['M' => 5]]);

        $this->actingAs($this->admin())->post(route('admin.productions.day.start', $prod));
        $this->saveStage($prod, ['action' => 'save', 'size_qty' => ['L' => 3]]);
        $this->actingAs($this->admin())->post(route('admin.productions.day.finish', $prod));

        $this->assertSame(8, ProductionLog::where('jenis', ProductionLog::SELESAI_HARI)->sole()->output_qty);
    }

    public function test_next_day_starts_a_fresh_summary(): void
    {
        $prod = $this->sizedBatch();

        $this->actingAs($this->admin())->post(route('admin.productions.day.start', $prod));
        $this->saveStage($prod, ['action' => 'save', 'size_qty' => ['M' => 20]]);
        $this->actingAs($this->admin())->post(route('admin.productions.day.finish', $prod));

        $this->actingAs($this->admin())->post(route('admin.productions.day.start', $prod));
        $this->saveStage($prod, ['action' => 'save', 'size_qty' => ['L' => 7]]);
        $this->actingAs($this->admin())->post(route('admin.productions.day.finish', $prod));

        $this->assertSame(
            ['Selesai mengerjakan Cutting, total 20 pcs: M 20 pcs.', 'Selesai mengerjakan Cutting, total 7 pcs: L 7 pcs.'],
            ProductionLog::where('jenis', ProductionLog::SELESAI_HARI)->orderBy('id')->pluck('keterangan')->all()
        );
    }

    public function test_a_day_with_no_pcs_can_still_be_closed(): void
    {
        $prod = $this->sizedBatch();

        $this->actingAs($this->admin())->post(route('admin.productions.day.start', $prod));
        $this->actingAs($this->admin())->post(route('admin.productions.day.finish', $prod))->assertSessionHas('success');

        $this->assertSame('Selesai hari ini tanpa pcs yang dicatat.', ProductionLog::where('jenis', ProductionLog::SELESAI_HARI)->sole()->keterangan);
    }

    public function test_finish_day_popup_shows_the_summary_text(): void
    {
        $prod = $this->sizedBatch();
        $this->actingAs($this->admin())->post(route('admin.productions.day.start', $prod));
        $this->saveStage($prod, ['action' => 'save', 'size_qty' => ['M' => 30, 'L' => 20]]);

        $this->showPage($prod)
            ->assertSee('id="modal-konfirmasi-harian"', false)
            ->assertSee('data-summary="Selesai mengerjakan Cutting, total 50 pcs: M 30 pcs, L 20 pcs."', false);
    }

    public function test_unsized_pcs_are_counted_without_a_size_breakdown(): void
    {
        $prod = $this->unsizedBatch();
        $this->actingAs($this->admin())->post(route('admin.productions.day.start', $prod));
        $this->saveStage($prod, ['action' => 'save', 'qty' => 25]);
        $this->actingAs($this->admin())->post(route('admin.productions.day.finish', $prod));

        $this->assertSame('Selesai mengerjakan Cutting, total 25 pcs.', ProductionLog::where('jenis', ProductionLog::SELESAI_HARI)->sole()->keterangan);
    }

    public function test_mulai_is_refused_for_a_closed_batch(): void
    {
        $prod = $this->sizedBatch();
        $prod->forceFill(['status' => 'cancelled'])->save();

        $this->actingAs($this->admin())->post(route('admin.productions.day.start', $prod))->assertSessionHas('error');

        $this->assertSame(0, ProductionLog::count());
    }

    public function test_old_log_rows_are_read_by_their_size_text(): void
    {
        $prod = $this->sizedBatch();
        $prod->logs()->create([
            'production_stage_id' => $this->stage($prod)->id, 'waktu' => now(),
            'input_qty' => 80, 'output_qty' => 50, 'sisa_qty' => 30, 'keterangan' => 'M 30, L 20',
        ]);

        $log = ProductionLog::sole();
        $this->assertSame(ProductionLog::HASIL, $log->jenis);
        $this->assertSame(['M' => 30, 'L' => 20], $log->sizeQty());
    }
}
