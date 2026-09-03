<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
