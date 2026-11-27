<?php

namespace App\Http\Controllers;

use App\Services\Scheduling\OutletAccess;
use App\Services\Scheduling\ScheduleRules;
use App\Services\Scheduling\ScheduleService;
use App\Services\Swaps\SwapService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class ScheduleController
{
    public function index(Request $request, ScheduleService $service, SwapService $swaps): Response
    {
        $actor = $request->user();
        $outlet = OutletAccess::require($actor);
        $data = $request->validate(['week' => 'nullable|date_format:Y-m-d']);
        $today = CarbonImmutable::now($outlet->timezone);
        $week = CarbonImmutable::parse($data['week'] ?? $today->toDateString())->startOfWeek()->toDateString();
        $dates = ScheduleRules::dates($week);
        $record = DB::table('schedule_weeks')->where(['outlet_id' => $outlet->id, 'monday' => $week])->first();
        $members = $service->members($outlet);
        $days = $service->days($outlet->id, $dates[0], $dates[6]);
        $reservations = [];
        $localTime = $today->format('Y-m-d H:i');
        if ($actor->role === 'supervisor') {
            DB::transaction(function () use ($outlet, $swaps) {
                DB::table('outlets')->lockForUpdate()->find($outlet->id);
                $swaps->expire($outlet->id);
            });
            $reservations = DB::table('schedule_reservations')->whereIn('schedule_day_id', $days->pluck('id'))->pluck('swap_id', 'schedule_day_id')->all();
        }

        return Inertia::render('Schedule', [
            'week' => $week, 'version' => $record?->version ?? 0, 'outlet' => $outlet,
            'days' => $days, 'members' => array_values($members), 'dates' => $dates,
            'reservations' => (object) $reservations, 'localTime' => $localTime,
            'previous' => CarbonImmutable::parse($week)->subWeek()->toDateString(),
            'next' => CarbonImmutable::parse($week)->addWeek()->toDateString(),
        ]);
    }

    public function store(Request $request, ScheduleService $service): RedirectResponse
    {
        $data = $request->validate([
            'week' => 'required|date_format:Y-m-d', 'version' => 'required|integer|min:0',
            'cells' => 'required|array', 'cells.*' => 'array', 'cells.*.*' => ['required', Rule::in(ScheduleRules::SHIFTS)],
            'reason' => 'nullable|string|max:1000', 'confirmed' => 'array', 'confirmed.*' => 'integer',
        ]);
        if (! CarbonImmutable::parse($data['week'])->isMonday()) {
            throw new DomainException('Tanggal awal jadwal harus hari Senin.');
        }
        $service->save($request->user(), $data['week'], (int) $data['version'], $data['cells'], $data['reason'] ?? null, $data['confirmed'] ?? []);

        return redirect()->route('schedule', ['week' => $data['week']])->with('message', 'Jadwal tersimpan.');
    }
}
