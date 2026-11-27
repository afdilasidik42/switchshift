<?php

namespace App\Services\Swaps;

use App\Models\User;
use App\Services\Audit\Activity;
use App\Services\Scheduling\OutletAccess;
use App\Services\Scheduling\ScheduleService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use stdClass;

final class SwapService
{
    private const OPEN = ['pending_peer', 'pending_supervisor'];

    public function __construct(private ScheduleService $schedule) {}

    public function propose(User $actor, int $ownId, int $peerId, ?string $reason): array
    {
        $outlet = OutletAccess::require($actor, 'employee');

        return DB::transaction(function () use ($actor, $outlet, $ownId, $peerId, $reason) {
            $outlet = DB::table('outlets')->lockForUpdate()->find($outlet->id);
            $this->expire($outlet->id);
            $all = DB::table('schedule_days as d')->join('schedule_weeks as w', 'w.id', '=', 'd.schedule_week_id')
                ->where('w.outlet_id', $outlet->id)->whereIn('d.id', [$ownId, $peerId])->get(['d.*'])->keyBy('id');
            $own = $all->get($ownId);
            $peer = $all->get($peerId);
            abort_unless($own && $peer && $own->user_id === $actor->id, 404);
            $people = $this->eligible($outlet, $own->user_id, $peer->user_id, $own->position_id);
            $dates = [$own->work_date, $peer->work_date];
            $rows = $this->schedule->days($outlet->id, min($dates), max($dates))
                ->whereIn('user_id', [$own->user_id, $peer->user_id])->whereIn('work_date', $dates)
                ->keyBy(fn ($row) => "{$row->user_id}:{$row->work_date}");
            $plan = SwapPlan::build($own->user_id, $peer->user_id, $own->work_date, $peer->work_date, ScheduleService::matrix($rows));
            $deadline = CarbonImmutable::instance(SwapPlan::deadline($plan['first_work_date'], $outlet->timezone))->utc();
            $this->checkDeadline($deadline);
            $changes = [];
            foreach ($plan['changes'] as $change) {
                $row = $rows->get("{$change['user_id']}:{$change['date']}");
                $person = $people[$change['user_id']];
                if ($row->position_id !== $own->position_id) {
                    throw new DomainException('Posisi pada jadwal sudah berubah.');
                }
                $changes[] = [...$change, 'id' => $row->id, 'version' => $row->version, 'week' => $row->monday,
                    'name' => $person['name'], 'position' => $person['position_name']];
            }
            $this->checkReserved(array_column($changes, 'id'));
            $this->schedule->validateChanges($outlet, $changes, array_column($changes, 'week'));

            if ($reason !== null) {
                $this->checkDeadline($deadline);
                $id = DB::table('swaps')->insertGetId([
                    'outlet_id' => $outlet->id, 'requester_id' => $own->user_id, 'partner_id' => $peer->user_id,
                    'position_id' => $own->position_id, 'status' => 'pending_peer', 'reason' => $reason,
                    'deadline' => $deadline->format('Y-m-d H:i:s'), 'created_at' => now(), 'updated_at' => now(),
                ]);
                foreach ($changes as $change) {
                    DB::table('swap_days')->insert([
                        'swap_id' => $id, 'schedule_day_id' => $change['id'], 'before' => $change['before'],
                        'after' => $change['after'], 'base_version' => $change['version'],
                    ]);
                }
                $this->reserve($id, array_column($changes, 'id'));
                Activity::audit('swap_submitted', $actor->id, $id, reason: $reason);
                Activity::notify([$peer->user_id], 'Ada permintaan pertukaran untukmu.', "/pertukaran/{$id}");
            }

            return ['changes' => $changes, 'deadline' => $deadline->format(DATE_ATOM)];
        }, 3);
    }

    public function act(User $actor, int $id, string $action, ?string $reason): void
    {
        $outlet = OutletAccess::require($actor);
        $expired = DB::transaction(function () use ($actor, $id, $action, $reason, $outlet) {
            $outlet = DB::table('outlets')->lockForUpdate()->find($outlet->id);
            $swap = DB::table('swaps')->where('outlet_id', $outlet->id)->find($id);
            abort_unless($swap && $outlet->active, 404);
            $supervisor = OutletAccess::supervisor($outlet->id);
            $parties = [$swap->requester_id, $swap->partner_id];
            abort_unless($actor->id === $supervisor || in_array($actor->id, $parties, true), 403);
            $this->expire($outlet->id);
            $deadline = CarbonImmutable::parse($swap->deadline, 'UTC');
            if ($deadline->lte(now())) {
                return true;
            }
            $cancel = DB::table('cancellations')->where('swap_id', $id)->whereIn('status', self::OPEN)->first();
            if ($action === 'request_cancellation') {
                abort_unless(in_array($actor->id, $parties, true), 403);
                if ($swap->status !== 'approved' || $cancel) {
                    throw new DomainException('Pembatalan tidak tersedia untuk status ini.');
                }
                $changes = $this->changes($swap, $outlet, true);
                $dayIds = array_column($changes, 'id');
                $this->checkReserved($dayIds);
                $this->checkDeadline($deadline);
                $cancelId = DB::table('cancellations')->insertGetId([
                    'swap_id' => $id, 'requester_id' => $actor->id, 'status' => 'pending_peer',
                    'reason' => $reason, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $this->reserve($id, $dayIds);
                Activity::audit($action, $actor->id, $id, $cancelId, $reason);
                Activity::notify(array_diff($parties, [$actor->id]), 'Ada permintaan pembatalan untukmu.', "/pertukaran/{$id}");

                return false;
            }

            $reversal = str_starts_with($action, 'cancellation_');
            $decision = $reversal ? substr($action, 13) : $action;
            $record = $reversal ? $cancel : $swap;
            if (! $record || ! in_array($record->status, self::OPEN, true)) {
                throw new DomainException('Status sudah berubah. Muat ulang detail pengajuan.');
            }
            $initiator = $record->requester_id;
            $peer = $initiator === $swap->requester_id ? $swap->partner_id : $swap->requester_id;
            $expectedActor = match ($decision) {
                'withdraw' => $initiator,
                default => $record->status === 'pending_peer' ? $peer : $supervisor,
            };
            abort_unless($actor->id === $expectedActor, 403);
            if ($decision === 'accept') {
                $changes = $this->changes($swap, $outlet, $reversal);
                $status = $record->status === 'pending_peer' ? 'pending_supervisor' : 'approved';
            } else {
                $status = $decision === 'withdraw' ? 'withdrawn' : 'rejected';
            }
            $this->checkDeadline($deadline);

            if ($status === 'approved') {
                $weekIds = [];
                foreach ($changes as $change) {
                    DB::table('schedule_days')->where('id', $change['id'])->increment('version', 1, ['shift' => $change['after']]);
                    $weekIds[] = $change['week_id'];
                }
                DB::table('schedule_weeks')->whereIn('id', array_unique($weekIds))->increment('version');
                $this->schedule->record($outlet, $actor->id, $reversal ? 'pemulihan' : 'pertukaran',
                    $reversal ? 'Pemulihan pertukaran #'.$id : 'Persetujuan pertukaran #'.$id, $changes, $id);
                if ($reversal) {
                    DB::table('swaps')->where('id', $id)->update(['status' => 'reversed', 'updated_at' => now()]);
                }
            }
            DB::table($reversal ? 'cancellations' : 'swaps')->where('id', $record->id)->update(['status' => $status, 'updated_at' => now()]);
            if ($status !== 'pending_supervisor') {
                DB::table('schedule_reservations')->where('swap_id', $id)->delete();
            }
            Activity::audit(($reversal ? 'cancellation_' : 'swap_').$status, $actor->id, $id, $cancel?->id, $reason);
            Activity::notify([...$parties, $status === 'pending_supervisor' ? $supervisor : null],
                'Status pengajuan jadwal diperbarui.', "/pertukaran/{$id}");

            return false;
        }, 3);

        if ($expired) {
            throw new DomainException('Pengajuan sudah kedaluwarsa.');
        }
    }

    private function changes(stdClass $swap, stdClass $outlet, bool $reverse): array
    {
        $people = $this->eligible($outlet, $swap->requester_id, $swap->partner_id, $swap->position_id);
        $rows = DB::table('swap_days as s')->join('schedule_days as d', 's.schedule_day_id', '=', 'd.id')
            ->join('schedule_weeks as w', 'd.schedule_week_id', '=', 'w.id')->where('s.swap_id', $swap->id)
            ->get(['s.*', 'd.version', 'd.shift', 'd.user_id', 'd.work_date', 'd.schedule_week_id', 'w.monday']);
        $changes = [];
        foreach ($rows as $row) {
            $before = $reverse ? $row->after : $row->before;
            if ($row->version !== $row->base_version + (int) $reverse || $row->shift !== $before) {
                throw new DomainException('Jadwal telah direvisi. Snapshot pengajuan ini tidak berlaku.');
            }
            $changes[] = [
                'id' => $row->schedule_day_id, 'user_id' => $row->user_id, 'date' => $row->work_date,
                'week_id' => $row->schedule_week_id, 'week' => $row->monday,
                'before' => $before, 'after' => $reverse ? $row->before : $row->after,
                'name' => $people[$row->user_id]['name'], 'position' => $people[$row->user_id]['position_name'],
            ];
        }
        $this->schedule->validateChanges($outlet, $changes, array_column($changes, 'week'));

        return $changes;
    }

    private function eligible(stdClass $outlet, int $first, int $second, int $position): array
    {
        $people = $this->schedule->members($outlet);
        if (! $outlet->active || ! OutletAccess::supervisor($outlet->id) || $first === $second) {
            throw new DomainException('Outlet atau rekan belum memenuhi syarat pertukaran.');
        }
        foreach ([$first, $second] as $id) {
            $person = $people[$id] ?? null;
            if (! $person || $person['inactive'] || $person['position_id'] !== $position) {
                throw new DomainException('Kedua karyawan harus aktif dengan posisi dan outlet yang sama.');
            }
        }

        return $people;
    }

    private function checkDeadline(CarbonImmutable $deadline): void
    {
        if ($deadline->lte(now())) {
            throw new DomainException('Batas waktu H-1 telah lewat.');
        }
    }

    private function checkReserved(array $days): void
    {
        if (DB::table('schedule_reservations')->whereIn('schedule_day_id', $days)->exists()) {
            throw new DomainException('Salah satu hari masih terlibat dalam pengajuan aktif.');
        }
    }

    private function reserve(int $id, array $days): void
    {
        DB::table('schedule_reservations')->insert(array_map(fn ($day) => ['schedule_day_id' => $day, 'swap_id' => $id], $days));
    }

    /** Call while holding the outlet lock. */
    public function expire(int $outlet): void
    {
        $ids = DB::table('swaps')->where('outlet_id', $outlet)->where('deadline', '<=', now())
            ->whereIn('id', DB::table('schedule_reservations')->select('swap_id'))->pluck('id')->all();
        self::invalidate($ids, 'Batas waktu H-1 tercapai.', true);
    }

    public static function invalidate(array $ids, string $reason, bool $expired = false): void
    {
        foreach (DB::table('swaps')->whereIn('id', $ids)->get() as $swap) {
            $cancel = DB::table('cancellations')->where('swap_id', $swap->id)->whereIn('status', self::OPEN)->first();
            $supervisor = ($cancel?->status ?? $swap->status) === 'pending_supervisor' ? OutletAccess::supervisor($swap->outlet_id) : null;
            $state = $expired ? 'expired' : 'invalidated';
            if ($cancel) {
                DB::table('cancellations')->where('id', $cancel->id)->update(['status' => $state, 'updated_at' => now()]);
            } elseif (in_array($swap->status, self::OPEN, true)) {
                DB::table('swaps')->where('id', $swap->id)->update(['status' => $state, 'updated_at' => now()]);
            }
            DB::table('schedule_reservations')->where('swap_id', $swap->id)->delete();
            Activity::audit($state, null, $swap->id, $cancel?->id, $reason);
            Activity::notify([$swap->requester_id, $swap->partner_id, $supervisor], 'Pengajuan jadwal berakhir.', "/pertukaran/{$swap->id}");
        }
    }
}
