<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Production extends Model
{
    protected $fillable = [
        'product_id', 'user_id', 'production_machine_id', 'code', 'planned_qty', 'actual_qty',
        'start_date', 'end_date', 'status', 'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public const STATUSES = ['planned', 'in_progress', 'qc', 'completed', 'cancelled'];

    public function product() { return $this->belongsTo(Product::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function machine() { return $this->belongsTo(ProductionMachine::class, 'production_machine_id'); }
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
}
