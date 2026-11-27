<?php

namespace App\Services\Swaps;

use DateTimeImmutable;
use DateTimeZone;
use DomainException;

final class SwapPlan
{
    /**
     * Each date exchanges that day's values between the two employees.
     * Working shifts keep their date and start time.
     */
    public static function build(int $first, int $second, string $firstDate, string $secondDate, array $days): array
    {
        if ($first === $second) {
            throw new DomainException('Pilih rekan yang berbeda.');
        }

        $selectedFirst = $days[$first][$firstDate] ?? null;
        $selectedSecond = $days[$second][$secondDate] ?? null;
        if ($selectedFirst === 'libur' && $selectedSecond === 'libur') {
            throw new DomainException('Dua hari libur tidak dapat ditukar.');
        }

        $changes = [];
        $workingDates = [];
        foreach (array_unique([$firstDate, $secondDate]) as $date) {
            $left = $days[$first][$date] ?? null;
            $right = $days[$second][$date] ?? null;
            if ($left === null || $right === null) {
                throw new DomainException('Jadwal kedua karyawan harus tersedia pada semua tanggal terkait.');
            }
            if ($left === $right) {
                throw new DomainException("Jadwal kedua karyawan pada {$date} sama.");
            }
            if ($left !== 'libur' || $right !== 'libur') {
                $workingDates[] = $date;
            }
            $changes[] = ['user_id' => $first, 'date' => $date, 'before' => $left, 'after' => $right];
            $changes[] = ['user_id' => $second, 'date' => $date, 'before' => $right, 'after' => $left];
        }

        if ($firstDate !== $secondDate) {
            $firstGivesWork = ($selectedFirst !== 'libur' && $days[$second][$firstDate] !== $selectedFirst)
                || ($days[$first][$secondDate] !== 'libur' && $days[$first][$secondDate] !== $selectedSecond);
            $secondGivesWork = ($selectedSecond !== 'libur' && $days[$first][$secondDate] !== $selectedSecond)
                || ($days[$second][$firstDate] !== 'libur' && $days[$second][$firstDate] !== $selectedFirst);
            if (! $firstGivesWork || ! $secondGivesWork) {
                throw new DomainException('Kedua karyawan harus memberikan penugasan kerja dalam pertukaran beda tanggal.');
            }
        }

        return ['changes' => $changes, 'first_work_date' => min($workingDates)];
    }

    public static function deadline(string $date, string $timezone): DateTimeImmutable
    {
        return new DateTimeImmutable($date.' 00:00:00', new DateTimeZone($timezone));
    }
}
