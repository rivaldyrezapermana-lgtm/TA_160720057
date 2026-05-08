<?php

namespace App\Services;

/**
 * Fuzzy Mamdani implementation for production quantity recommendation.
 *
 * Inputs:
 *   - demand     (current period demand)
 *   - stock      (current stock on hand)
 *   - history    (array of sales records to derive min/max)
 *
 * Output:
 *   - recommended_production (int, units)
 *
 * Steps:
 *   1. Fuzzification: compute membership for "demand naik/turun" and "persediaan banyak/sedikit".
 *   2. Inference: 4 rules using AND (min) operator.
 *   3. Defuzzification: weighted average over rule outputs.
 *
 * Reference (in proposal Bab II): Djunaidi et al. 2005, Simajuntak & Fauzi 2017.
 */
class FuzzyMamdaniService
{
    /**
     * Run the full Mamdani pipeline.
     *
     * @param  float       $demand   permintaan periode saat ini
     * @param  float       $stock    persediaan saat ini
     * @param  array       $history  [['demand'=>n,'stock_end'=>n,'produced'=>n], ...]
     * @return array       Detailed result for display in the UI.
     */
    public function calculate(float $demand, float $stock, array $history): array
    {
        // ---- Derive ranges from history --------------------------------
        $demands  = array_column($history, 'demand');
        $stocks   = array_column($history, 'stock_end');
        $produced = array_column($history, 'produced');

        $dMin = min($demands ?: [0]);
        $dMax = max($demands ?: [1]);
        $sMin = min($stocks  ?: [0]);
        $sMax = max($stocks  ?: [1]);
        $pMin = min($produced ?: [0]);
        $pMax = max($produced ?: [1]);

        // ---- 1. Fuzzification ------------------------------------------
        // Demand
        $demandTurun = $this->descending($demand, $dMin, $dMax);  // µPermintaan_TURUN
        $demandNaik  = $this->ascending ($demand, $dMin, $dMax);  // µPermintaan_NAIK
        // Stock
        $stockSedikit = $this->descending($stock, $sMin, $sMax);  // µPersediaan_SEDIKIT
        $stockBanyak  = $this->ascending ($stock, $sMin, $sMax);  // µPersediaan_BANYAK

        // ---- 2. Rule Inference (Mamdani uses MIN for AND) --------------
        // Rule 1: IF demand TURUN  AND stock BANYAK   THEN production BERKURANG
        // Rule 2: IF demand TURUN  AND stock SEDIKIT  THEN production BERKURANG
        // Rule 3: IF demand NAIK   AND stock BANYAK   THEN production BERTAMBAH
        // Rule 4: IF demand NAIK   AND stock SEDIKIT  THEN production BERTAMBAH
        $alpha1 = min($demandTurun, $stockBanyak);
        $alpha2 = min($demandTurun, $stockSedikit);
        $alpha3 = min($demandNaik,  $stockBanyak);
        $alpha4 = min($demandNaik,  $stockSedikit);

        // Aggregate per output set (take the max alpha for each consequent)
        $alphaBerkurang = max($alpha1, $alpha2);
        $alphaBertambah = max($alpha3, $alpha4);

        // ---- 3. Defuzzification ----------------------------------------
        // Crisp z values for each consequent:
        //   z_berkurang corresponds to the lower edge of production range
        //   z_bertambah corresponds to the upper edge
        // Following the proposal's example: midpoint of (max - min)/2 + min
        $zBerkurang = $pMin + ($pMax - $pMin) * 0.25;   // lower quartile
        $zBertambah = $pMin + ($pMax - $pMin) * 0.85;   // upper region

        $numerator   = ($alphaBerkurang * $zBerkurang) + ($alphaBertambah * $zBertambah);
        $denominator = $alphaBerkurang + $alphaBertambah;

        $z = $denominator > 0 ? $numerator / $denominator : 0;

        return [
            'inputs' => [
                'demand' => $demand,
                'stock'  => $stock,
            ],
            'ranges' => [
                'demand_min'   => $dMin, 'demand_max'   => $dMax,
                'stock_min'    => $sMin, 'stock_max'    => $sMax,
                'produced_min' => $pMin, 'produced_max' => $pMax,
            ],
            'fuzzification' => [
                'demand_turun'  => round($demandTurun, 4),
                'demand_naik'   => round($demandNaik, 4),
                'stock_sedikit' => round($stockSedikit, 4),
                'stock_banyak'  => round($stockBanyak, 4),
            ],
            'rules' => [
                'R1_turun_banyak_berkurang'   => round($alpha1, 4),
                'R2_turun_sedikit_berkurang'  => round($alpha2, 4),
                'R3_naik_banyak_bertambah'    => round($alpha3, 4),
                'R4_naik_sedikit_bertambah'   => round($alpha4, 4),
            ],
            'aggregated' => [
                'alpha_berkurang' => round($alphaBerkurang, 4),
                'alpha_bertambah' => round($alphaBertambah, 4),
                'z_berkurang'     => round($zBerkurang, 2),
                'z_bertambah'     => round($zBertambah, 2),
            ],
            'recommended_production' => (int) round($z),
        ];
    }

    /** Descending linear membership: 1 at min, 0 at max. */
    private function descending(float $x, float $min, float $max): float
    {
        if ($max <= $min) return 0;
        if ($x <= $min)   return 1;
        if ($x >= $max)   return 0;
        return ($max - $x) / ($max - $min);
    }

    /** Ascending linear membership: 0 at min, 1 at max. */
    private function ascending(float $x, float $min, float $max): float
    {
        if ($max <= $min) return 0;
        if ($x <= $min)   return 0;
        if ($x >= $max)   return 1;
        return ($x - $min) / ($max - $min);
    }
}
