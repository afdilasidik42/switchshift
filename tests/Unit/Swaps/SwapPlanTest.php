<?php

namespace Tests\Unit\Swaps;

use App\Services\Swaps\SwapPlan;
use DomainException;
use PHPUnit\Framework\TestCase;

final class SwapPlanTest extends TestCase
{
    public function test_work_and_rest_across_dates_changes_four_cells(): void
    {
        $days = [
            10 => ['2026-12-07' => 'pagi', '2026-12-08' => 'libur'],
            11 => ['2026-12-07' => 'libur', '2026-12-08' => 'sore'],
        ];
        $plan = SwapPlan::build(10, 11, '2026-12-07', '2026-12-08', $days);
        self::assertSame('2026-12-07', $plan['first_work_date']);
        self::assertSame([
            ['user_id' => 10, 'date' => '2026-12-07', 'before' => 'pagi', 'after' => 'libur'],
            ['user_id' => 11, 'date' => '2026-12-07', 'before' => 'libur', 'after' => 'pagi'],
            ['user_id' => 10, 'date' => '2026-12-08', 'before' => 'libur', 'after' => 'sore'],
            ['user_id' => 11, 'date' => '2026-12-08', 'before' => 'sore', 'after' => 'libur'],
        ], $plan['changes']);
    }

    public function test_two_rest_days_are_rejected(): void
    {
        $this->expectException(DomainException::class);
        SwapPlan::build(10, 11, '2026-12-07', '2026-12-07', [10 => ['2026-12-07' => 'libur'], 11 => ['2026-12-07' => 'libur']]);
    }

    public function test_deadline_uses_outlet_midnight(): void
    {
        $deadline = SwapPlan::deadline('2026-12-07', 'Asia/Makassar');
        self::assertSame('2026-12-06 16:00:00', $deadline->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
    }
}
