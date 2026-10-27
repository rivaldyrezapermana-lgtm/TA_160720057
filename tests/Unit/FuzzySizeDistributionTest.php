<?php

namespace Tests\Unit;

use App\Services\FuzzyMamdaniService;
use PHPUnit\Framework\TestCase;

class FuzzySizeDistributionTest extends TestCase
{
    public function test_splits_total_proportionally_to_sales(): void
    {
        $rows = (new FuzzyMamdaniService)->distributeBySize(100, ['S' => 10, 'M' => 30, 'L' => 40, 'XL' => 20]);

        $this->assertSame([10, 30, 40, 20], array_column($rows, 'qty'));
        $this->assertSame(['S', 'M', 'L', 'XL'], array_column($rows, 'size'));
    }

    public function test_parts_always_sum_to_total(): void
    {
        $rows = (new FuzzyMamdaniService)->distributeBySize(4639, ['S' => 1, 'M' => 1, 'L' => 1]);

        $this->assertSame(4639, array_sum(array_column($rows, 'qty')));
    }

    public function test_falls_back_to_even_split_without_sales(): void
    {
        $rows = (new FuzzyMamdaniService)->distributeBySize(10, ['S' => 0, 'M' => 0, 'L' => 0, 'XL' => 0]);

        $this->assertSame(10, array_sum(array_column($rows, 'qty')));
        $this->assertEqualsWithDelta(0.25, $rows[0]['share'], 0.0001);
    }

    public function test_product_without_sizes_has_no_breakdown(): void
    {
        $this->assertSame([], (new FuzzyMamdaniService)->distributeBySize(50, []));
    }
}
