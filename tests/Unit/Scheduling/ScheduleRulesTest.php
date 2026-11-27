<?php

namespace Tests\Unit\Scheduling;

use App\Services\Scheduling\ScheduleRules;
use DomainException;
use PHPUnit\Framework\TestCase;

final class ScheduleRulesTest extends TestCase
{
    public function test_friday_joiner_must_work_until_sunday(): void
    {
        $people = [1 => ['name' => 'Baru', 'joined' => '2026-11-27', 'inactive' => null, 'position' => 'KASIR']];
        $days = [1 => ['2026-11-27' => 'pagi', '2026-11-28' => 'pagi', '2026-11-29' => 'libur']];
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('tepat 0 hari libur');
        (new ScheduleRules)->validate('2026-11-23', $people, $days);
    }
}
