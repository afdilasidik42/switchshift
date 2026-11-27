<?php

namespace App\Http\Controllers;

use App\Services\Scheduling\OutletAccess;
use App\Services\Swaps\SwapService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class SwapController
{
    public function index(Request $request, SwapService $service, ?int $id = null): Response
    {
        $actor = $request->user();
        $outlet = OutletAccess::require($actor);
        DB::transaction(function () use ($service, $outlet) {
            DB::table('outlets')->lockForUpdate()->find($outlet->id);
            $service->expire($outlet->id);
        });
        $filter = $request->validate(['status' => 'nullable|string|max:24', 'search' => 'nullable|integer', 'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d']);
        $list = DB::table('swaps as s')->join('users as a', 's.requester_id', '=', 'a.id')->join('users as b', 's.partner_id', '=', 'b.id')
            ->join('positions as p', 's.position_id', '=', 'p.id')->where('s.outlet_id', $outlet->id)
            ->when($actor->role === 'employee', fn ($query) => $query->where(fn ($query) => $query->where('s.requester_id', $actor->id)->orWhere('s.partner_id', $actor->id)))
            ->select('s.*', 'a.name as requester', 'b.name as partner', 'p.name as position');
        $detail = $id ? (clone $list)->where('s.id', $id)->first() : null;
        abort_if($id && ! $detail, 404);
        $options = [];
        if ($actor->role === 'employee' && ! $id) {
            $today = CarbonImmutable::now($outlet->timezone)->toDateString();
            $options = DB::table('schedule_days as d')->join('schedule_weeks as w', 'd.schedule_week_id', '=', 'w.id')
                ->join('users as u', 'd.user_id', '=', 'u.id')->where('w.outlet_id', $outlet->id)
                ->where('d.work_date', '>', $today)->where('d.position_id', $actor->position_id)
                ->where('u.position_id', $actor->position_id)->whereNull('u.disabled_at')->where('u.role', 'employee')
                ->whereNotIn('d.id', DB::table('schedule_reservations')->select('schedule_day_id'))
                ->orderBy('d.work_date')->limit(350)->get(['d.id', 'd.work_date', 'd.shift', 'u.name', 'd.user_id']);
        }

        return Inertia::render('Swaps', [
            'outlet' => $outlet, 'options' => $options,
            'items' => $list->when($filter['status'] ?? null, fn ($q, $status) => $q->where('s.status', $status))
                ->when($filter['search'] ?? null, fn ($q, $search) => $q->where('s.id', $search))
                ->when($filter['from'] ?? null, fn ($q, $date) => $q->whereDate('s.created_at', '>=', $date))
                ->when($filter['to'] ?? null, fn ($q, $date) => $q->whereDate('s.created_at', '<=', $date))
                ->orderByDesc('s.id')->paginate(25)->withQueryString(),
            'filter' => $filter, 'detail' => $detail,
            'changes' => $id ? DB::table('swap_days as s')->join('schedule_days as d', 'd.id', '=', 's.schedule_day_id')
                ->join('users as u', 'u.id', '=', 'd.user_id')->where('s.swap_id', $id)
                ->orderBy('d.work_date')->get(['s.before', 's.after', 'd.work_date as date', 'u.name']) : [],
            'cancellations' => $id ? DB::table('cancellations')->where('swap_id', $id)->latest('id')->get() : [],
            'timeline' => $id ? DB::table('audit_events as e')->leftJoin('users as u', 'e.actor_id', '=', 'u.id')
                ->where('e.swap_id', $id)->orderBy('e.id')->get(['e.id', 'e.event', 'e.reason', 'e.created_at', 'u.name']) : [],
            'preview' => $request->session()->get('swap_preview'),
        ]);
    }

    public function submit(Request $request, SwapService $service): RedirectResponse
    {
        $data = $request->validate([
            'own_day' => 'required|integer|min:1', 'peer_day' => 'required|integer|min:1',
            'preview' => 'required|boolean', 'reason' => 'nullable|required_unless:preview,true,1|string|max:1000',
        ]);
        $preview = $request->boolean('preview');
        $result = $service->propose($request->user(), (int) $data['own_day'], (int) $data['peer_day'], $preview ? null : $data['reason']);

        return $preview
            ? back()->with('swap_preview', [...$result, 'own_day' => (int) $data['own_day'], 'peer_day' => (int) $data['peer_day']])
            : redirect('/pertukaran')->with('message', 'Pertukaran diajukan.');
    }

    public function act(Request $request, SwapService $service, int $id): RedirectResponse
    {
        $data = $request->validate([
            'action' => 'required|in:accept,reject,withdraw,request_cancellation,cancellation_accept,cancellation_reject,cancellation_withdraw',
            'reason' => 'nullable|required_unless:action,accept,cancellation_accept|string|max:1000',
        ]);
        $service->act($request->user(), $id, $data['action'], $data['reason'] ?? null);

        return back()->with('message', 'Keputusan tersimpan.');
    }
}
