<?php

namespace App\Http\Controllers;

use App\Services\Scheduling\OutletAccess;
use App\Services\Scheduling\ScheduleService;
use App\Services\Swaps\SwapService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class InformationController
{
    public function dashboard(Request $request, ScheduleService $schedules, SwapService $swaps): Response
    {
        $actor = $request->user();
        $outlet = OutletAccess::resolve($actor);
        $today = CarbonImmutable::now($outlet?->timezone ?? 'Asia/Jakarta');
        $agenda = $queue = $summary = $shortages = [];
        if ($actor->role === 'admin') {
            $summary = [
                'Outlet aktif' => DB::table('outlets')->where('active', true)->count(),
                'Akun aktif' => DB::table('users')->whereNull('disabled_at')->count(),
                'Outlet tanpa SPV aktif' => DB::table('outlets')->where('active', true)->whereNotIn('id',
                    DB::table('supervisor_assignments as a')->join('users as u', 'u.id', '=', 'a.user_id')->whereNull('u.disabled_at')->select('a.outlet_id'))->count(),
            ];
        } elseif ($outlet) {
            DB::transaction(function () use ($swaps, $outlet) {
                DB::table('outlets')->lockForUpdate()->find($outlet->id);
                $swaps->expire($outlet->id);
            });
            $people = $schedules->members($outlet);
            $days = $schedules->days($outlet->id, $today->toDateString(), $today->addDays(6)->toDateString());
            $agenda = $actor->role === 'employee' ? $days->where('user_id', $actor->id)->values() : [];
            if ($actor->role === 'supervisor') {
                $shortages = $days->filter(fn ($day) => $day->shift !== 'libur'
                    && ($people[$day->user_id]['inactive'] ?? '9999-12-31') <= $day->work_date)
                    ->map(fn ($day) => ['id' => $day->id, 'date' => $day->work_date, 'shift' => $day->shift, 'name' => $people[$day->user_id]['name']])->values();
            }
            $queue = DB::table('swaps as s')->where('s.outlet_id', $outlet->id)
                ->leftJoin('cancellations as c', fn ($join) => $join->on('c.swap_id', '=', 's.id')->whereIn('c.status', ['pending_peer', 'pending_supervisor']))
                ->when($actor->role === 'employee', fn ($q) => $q->where(fn ($q) => $q->where('s.requester_id', $actor->id)->orWhere('s.partner_id', $actor->id)))
                ->when($actor->role === 'supervisor', fn ($q) => $q->where(fn ($q) => $q->where('s.status', 'pending_supervisor')->orWhere('c.status', 'pending_supervisor')))
                ->orderByDesc('s.id')->limit(10)->get(['s.id', 's.status', 's.deadline', 'c.status as cancellation_status']);
        }

        return Inertia::render('Dashboard', [
            'outlet' => $outlet, 'today' => $today->toDateString(), 'summary' => (object) $summary,
            'agenda' => $agenda, 'queue' => $queue, 'shortages' => $shortages,
            'pending' => $this->confirmations($actor, $outlet?->id)->whereNull('a.confirmed_at')->count(),
        ]);
    }

    public function notifications(Request $request): Response
    {
        $unread = $request->boolean('unread');
        $items = DB::table('notifications')->where('user_id', $request->user()->id)
            ->when($unread, fn ($q) => $q->whereNull('read_at'))->latest('id')->paginate(25)->withQueryString();

        return Inertia::render('Notifications', ['items' => $items, 'unreadOnly' => $unread]);
    }

    public function read(Request $request, ?int $id = null): RedirectResponse
    {
        $query = DB::table('notifications')->where('user_id', $request->user()->id);
        if ($id !== null) {
            $query->where('id', $id);
            abort_unless($query->exists(), 404);
        }
        $query->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }

    public function history(Request $request): Response
    {
        $actor = $request->user();
        $outlet = OutletAccess::require($actor);
        $filter = $request->validate([
            'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d',
            'id' => 'nullable|integer', 'kind' => 'nullable|in:penerbitan,revisi,pertukaran,pemulihan',
        ]);
        $items = DB::table('schedule_changes as c')->join('users as u', 'c.actor_id', '=', 'u.id')->where('c.outlet_id', $outlet->id)
            ->when($actor->role === 'employee', fn ($q) => $q->whereIn('c.id', DB::table('acknowledgements')->where('user_id', $actor->id)->select('schedule_change_id')))
            ->when($filter['from'] ?? null, fn ($q, $date) => $q->whereDate('c.created_at', '>=', $date))
            ->when($filter['to'] ?? null, fn ($q, $date) => $q->whereDate('c.created_at', '<=', $date))
            ->when($filter['id'] ?? null, fn ($q, $id) => $q->where('c.id', $id))
            ->when($filter['kind'] ?? null, fn ($q, $kind) => $q->where('c.kind', $kind))
            ->select('c.*', 'u.name as actor')->orderByDesc('c.id')->paginate(15)->withQueryString();
        $acks = $this->confirmations($actor, $outlet->id)->whereIn('a.schedule_change_id', $items->pluck('id'))
            ->join('users as au', 'a.user_id', '=', 'au.id')->get(['a.*', 'au.name'])->groupBy('schedule_change_id');
        $items->through(function ($item) use ($actor, $acks) {
            $changes = json_decode($item->details, true, flags: JSON_THROW_ON_ERROR);
            if ($actor->role === 'employee') {
                $changes = array_values(array_filter($changes, fn ($change) => $change['user_id'] === $actor->id));
                $item->reason = null;
            }
            $item->details = $changes;
            $item->acknowledgements = $acks->get($item->id, []);

            return $item;
        });

        return Inertia::render('History', ['items' => $items, 'filter' => $filter, 'outlet' => $outlet]);
    }

    public function acknowledge(Request $request, int $id): RedirectResponse
    {
        $actor = $request->user();
        $outlet = OutletAccess::require($actor, 'employee');
        $query = DB::table('acknowledgements')->where('user_id', $actor->id)->where('schedule_change_id', $id)
            ->whereIn('schedule_change_id', DB::table('schedule_changes')->where('outlet_id', $outlet->id)->select('id'));
        abort_unless($query->exists(), 404);
        $query->whereNull('confirmed_at')->update(['confirmed_at' => now()]);

        return back()->with('message', 'Konfirmasi tersimpan.');
    }

    private function confirmations($actor, ?int $outlet): Builder
    {
        return DB::table('acknowledgements as a')->join('schedule_changes as c', 'c.id', '=', 'a.schedule_change_id')
            ->where('c.outlet_id', $outlet)
            ->when($actor->role !== 'supervisor', fn ($q) => $q->where('a.user_id', $actor->id));
    }
}
