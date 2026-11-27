<?php

namespace App\Services\Scheduling;

use DateTimeImmutable;
use DomainException;

final class ScheduleRules
{
    public const SHIFTS = ['pagi', 'sore', 'libur'];

    public const MINIMUM = ['KASIR' => 1, 'BAKER' => 2];

    public static function dates(string $monday): array
    {
        $start = new DateTimeImmutable($monday);

        return array_map(fn ($offset) => $start->modify("+{$offset} days")->format('Y-m-d'), range(0, 6));
    }

    public function validate(string $week, array $members, array $days): void
    {
        $dates = self::dates($week);
        $coverage = [];
        foreach ($members as $id => $member) {
            $off = 0;
            $activeDates = 0;
            foreach ($dates as $date) {
                if ($date < $member['joined'] || ($member['inactive'] && $date >= $member['inactive'])) {
                    continue;
                }
                $activeDates++;
                $shift = $days[$id][$date] ?? null;
                if (! in_array($shift, self::SHIFTS, true)) {
                    throw new DomainException("Isi jadwal {$member['name']} pada {$date}.");
                }
                if ($shift === 'libur') {
                    $off++;
                } else {
                    $position = $member['position'];
                    $coverage[$date][$shift][$position] = ($coverage[$date][$shift][$position] ?? 0) + 1;
                }
            }

            $firstWeek = $member['joined'] >= $dates[0] && $member['joined'] <= $dates[6];
            $expectedOff = $firstWeek && $member['joined'] >= $dates[4] ? 0 : 2;
            if ($activeDates && (! $member['inactive'] || $member['inactive'] > $dates[6]) && $off !== $expectedOff) {
                throw new DomainException("{$member['name']} harus mendapat tepat {$expectedOff} hari libur pada minggu {$week}.");
            }

            $start = (new DateTimeImmutable($week))->modify('-4 days');
            $streak = 0;
            for ($index = 0; $index < 15; $index++) {
                $date = $start->modify("+{$index} days")->format('Y-m-d');
                $worked = in_array($days[$id][$date] ?? null, ['pagi', 'sore'], true)
                    && $date >= $member['joined'] && (! $member['inactive'] || $date < $member['inactive']);
                $streak = $worked ? $streak + 1 : 0;
                if ($streak > 4) {
                    throw new DomainException("{$member['name']} bekerja lebih dari empat hari berturut-turut hingga {$date}.");
                }
            }
        }

        foreach ($dates as $date) {
            foreach (['pagi', 'sore'] as $shift) {
                foreach (self::MINIMUM as $position => $minimum) {
                    if (($coverage[$date][$shift][$position] ?? 0) < $minimum) {
                        throw new DomainException("{$date} shift {$shift} membutuhkan minimal {$minimum} {$position}.");
                    }
                }
            }
        }
    }
}
