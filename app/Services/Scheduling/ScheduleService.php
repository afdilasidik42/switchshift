<?php

namespace App\Services\Scheduling;

use App\Models\User;
use App\Services\Audit\Activity;
use App\Services\Swaps\SwapService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

final class ScheduleService
{
    public function __construct(private ScheduleRules $rules) {}

    public function members(stdClass $outlet): array
    {
        $members = [];
        $rows = DB::table('users as u')->join('positions as p', 'u.position_id', '=', 'p.id')
            ->where('u.outlet_id', $outlet->id)->where('u.role', 'employee')
            ->orderBy('u.name')->get(['u.id', 'u.name', 'u.joined_on', 'u.disabled_at', 'p.name as position_name', 'p.code', 'p.id as position_id']);
        foreach ($rows as $row) {
            $members[$row->id] = [
                'id' => $row->id, 'name' => $row->name, 'joined' => $row->joined_on,
                'inactive' => $row->disabled_at ? CarbonImmutable::parse($row->disabled_at, 'UTC')->setTimezone($outlet->timezone)->toDateString() : null,
                'position' => $row->code, 'position_name' => $row->position_name, 'position_id' => $row->position_id,
            ];
        }

        return $members;
    }

    public function days(int $outlet, string $from, string $to): Collection
    {
        return DB::table('schedule_days as d')->join('schedule_weeks as w', 'd.schedule_week_id', '=', 'w.id')
            ->where('w.outlet_id', $outlet)->whereBetween('d.work_date', [$from, $to])
            ->orderBy('d.work_date')->get(['d.*', 'w.monday']);
    }

    public static function matrix(iterable $days): array
    {
        $matrix = [];
        foreach ($days as $day) {
            $matrix[$day->user_id][$day->work_date] = $day->shift;
        }

        return $matrix;
    }

    public function validateChanges(stdClass $outlet, array $changes, array $weeks): void
    {
        $members = $this->members($outlet);
        foreach (array_unique($weeks) as $week) {
            $monday = CarbonImmutable::parse($week);
            $days = self::matrix($this->days($outlet->id, $monday->subDays(4)->toDateString(), $monday->addDays(10)->toDateString()));
            foreach ($changes as $change) {
                $days[$change['user_id']][$change['date']] = $change['after'];
            }
            $this->rules->validate($week, $members, $days);
        }
    }

    public function save(User $actor, string $week, int $version, array $input, ?string $reason, array $confirmed): void
    {
        $outlet = OutletAccess::require($actor, 'supervisor');
        DB::transaction(function () use ($actor, $week, $version, $input, $reason, $confirmed, $outlet) {
            $outlet = DB::table('outlets')->lockForUpdate()->find($outlet->id);
            abort_unless($outlet->active && OutletAccess::supervisor($outlet->id) === $actor->id, 403);
            $record = DB::table('schedule_weeks')->where(['outlet_id' => $outlet->id, 'monday' => $week])->first();
            if (($record?->version ?? 0) !== $version) {
                throw new DomainException('Jadwal sudah diperbarui. Muat ulang sebelum menyimpan.');
            }
            $dates = ScheduleRules::dates($week);
            $current = $this->days($outlet->id, $dates[0], $dates[6])->keyBy(fn ($day) => "{$day->user_id}:{$day->work_date}");
            $members = $this->members($outlet);
            $localNow = CarbonImmutable::now($outlet->timezone);
            $changes = [];
            foreach ($input as $id => $submittedDays) {
                foreach ($submittedDays as $date => $shift) {
                    $member = $members[$id] ?? null;
                    if (! $member || ! in_array($date, $dates, true) || $date < $member['joined'] || ($member['inactive'] && $date >= $member['inactive'])) {
                        throw new DomainException('Ada sel yang bukan penugasan aktif pada minggu ini.');
                    }
                    $prior = $current->get("{$id}:{$date}");
                    if ($prior?->shift === $shift) {
                        continue;
                    }
                    foreach ([$prior?->shift, $shift] as $affected) {
                        $start = match ($affected) {
                            'pagi' => '07:00', 'sore' => '15:00', default => null
                        };
                        if ($date < $localNow->toDateString() || ($start && CarbonImmutable::parse("{$date} {$start}", $outlet->timezone)->lte($localNow))) {
                            throw new DomainException("Jadwal {$member['name']} pada {$date} sudah lewat atau mulai.");
                        }
                    }
                    $changes[] = ['id' => $prior?->id, 'user_id' => (int) $id, 'name' => $member['name'],
                        'position_id' => $member['position_id'], 'position' => $member['position_name'],
                        'date' => $date, 'before' => $prior?->shift, 'after' => $shift];
                }
            }

            if (! $record) {
                foreach ($members as $id => $member) {
                    foreach ($dates as $date) {
                        if ($date >= $member['joined'] && (! $member['inactive'] || $date < $member['inactive']) && ! isset($input[$id][$date])) {
                            throw new DomainException("Isi jadwal {$member['name']} pada {$date}.");
                        }
                    }
                }
            }

            if (! $changes && $record) {
                return;
            }
            if ($record && ! $reason) {
                throw new DomainException('Isi alasan revisi jadwal.');
            }
            $this->validateChanges($outlet, $changes, [$week]);
            $dayIds = array_filter(array_column($changes, 'id'));
            $conflicts = DB::table('schedule_reservations')->whereIn('schedule_day_id', $dayIds)->distinct()->pluck('swap_id')->all();
            if (array_diff($conflicts, $confirmed)) {
                throw new DomainException('Konfirmasi pengajuan terdampak sebelum menyimpan: #'.implode(', #', $conflicts).'.');
            }
            SwapService::invalidate($conflicts, 'Jadwal direvisi oleh SPV.');
            $weekId = $record?->id ?? DB::table('schedule_weeks')->insertGetId(['outlet_id' => $outlet->id, 'monday' => $week]);
            foreach ($changes as $change) {
                if ($change['id']) {
                    DB::table('schedule_days')->where('id', $change['id'])->increment('version', 1, ['shift' => $change['after'], 'position_id' => $change['position_id']]);
                } else {
                    DB::table('schedule_days')->insert([
                        'schedule_week_id' => $weekId, 'user_id' => $change['user_id'], 'work_date' => $change['date'],
                        'position_id' => $change['position_id'], 'shift' => $change['after'],
                    ]);
                }
            }
            if ($record) {
                DB::table('schedule_weeks')->where('id', $weekId)->increment('version');
            }
            $this->record($outlet, $actor->id, $record ? 'revisi' : 'penerbitan', $reason ?? 'Penerbitan jadwal minggu '.$week, $changes);
        }, 3);
    }

    public function record(stdClass $outlet, int $actor, string $kind, string $reason, array $changes, ?int $swap = null): void
    {
        $id = DB::table('schedule_changes')->insertGetId([
            'outlet_id' => $outlet->id, 'actor_id' => $actor, 'kind' => $kind, 'reason' => $reason,
            'swap_id' => $swap, 'details' => json_encode($changes, JSON_THROW_ON_ERROR), 'created_at' => now(),
        ]);
        foreach (array_unique(array_column($changes, 'user_id')) as $user) {
            DB::table('acknowledgements')->insert(['schedule_change_id' => $id, 'user_id' => $user]);
        }
        $recipients = DB::table('users')->where('outlet_id', $outlet->id)->whereNull('disabled_at')->pluck('id');
        Activity::notify($recipients, 'Jadwal outlet telah diperbarui.', '/jadwal');
    }
}
