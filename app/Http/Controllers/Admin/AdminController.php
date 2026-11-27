<?php

namespace App\Http\Controllers\Admin;

use App\Services\Audit\Activity;
use App\Services\Scheduling\OutletAccess;
use App\Services\Swaps\SwapService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class AdminController
{
    private const ZONES = ['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura'];

    public function index(Request $request): Response
    {
        abort_unless($request->user()->role === 'admin', 403);

        return Inertia::render('Admin', [
            'outlets' => DB::table('outlets')->orderBy('name')->get(),
            'positions' => DB::table('positions')->orderBy('name')->get(),
            'users' => DB::table('users')->orderBy('name')->get(['id', 'employee_code', 'name', 'role', 'outlet_id', 'position_id', 'joined_on', 'disabled_at']),
            'assignments' => DB::table('supervisor_assignments')->get(),
            'zones' => self::ZONES,
            'swapCounts' => DB::table('swaps')->select('status')->selectRaw('COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function master(Request $request, string $type, ?int $id = null): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor->role === 'admin', 403);
        $rules = [
            'code' => ['required', 'alpha_dash:ascii', 'regex:/^[A-Z0-9_-]+$/', 'max:32', Rule::unique($type)->ignore($id)],
            'name' => 'required|string|max:120',
            'active' => 'required|boolean',
        ];
        if ($type === 'outlets') {
            $rules['timezone'] = ['required', Rule::in(self::ZONES)];
        }
        $data = $request->validate($rules);
        DB::transaction(function () use ($type, $id, $data, $actor) {
            if ($id === null) {
                $id = DB::table($type)->insertGetId($data);
                Activity::audit('master_created', $actor->id, details: ['type' => $type, 'id' => $id]);

                return;
            }
            $row = DB::table($type)->lockForUpdate()->find($id);
            abort_unless($row, 404);
            if ($type === 'positions') {
                if (in_array($row->code, ['KASIR', 'BAKER'], true) && ($row->code !== $data['code'] || ! $data['active'])) {
                    throw new DomainException('Kode Kasir dan Baker tetap aktif untuk validasi jadwal.');
                }
                if (! $data['active'] && DB::table('users')->where('position_id', $id)->whereNull('disabled_at')->exists()) {
                    throw new DomainException('Posisi masih digunakan karyawan aktif.');
                }
            } else {
                if (! $data['active'] && (DB::table('users')->where('outlet_id', $id)->whereNull('disabled_at')->exists()
                    || DB::table('supervisor_assignments')->where('outlet_id', $id)->exists()
                    || DB::table('swaps')->where('outlet_id', $id)->whereIn('status', ['pending_peer', 'pending_supervisor'])->exists()
                    || $this->futureSchedule($id))) {
                    throw new DomainException('Selesaikan penugasan dan jadwal mendatang sebelum menonaktifkan outlet.');
                }
                if ($row->timezone !== $data['timezone'] && DB::table('schedule_weeks')->where('outlet_id', $id)->exists()) {
                    throw new DomainException('Zona waktu dikunci setelah outlet memiliki jadwal.');
                }
            }
            DB::table($type)->where('id', $id)->update($data);
            Activity::audit('master_updated', $actor->id, details: ['type' => $type, 'id' => $id]);
        }, 3);

        return back()->with('message', 'Data master tersimpan.');
    }

    public function user(Request $request, ?int $id = null): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor->role === 'admin', 403);
        $existing = $id ? DB::table('users')->find($id) : null;
        abort_if($id && ! $existing, 404);
        $base = [
            'name' => 'required|string|max:120',
            'employee_code' => ['required', 'alpha_dash:ascii', 'max:32', Rule::unique('users')->ignore($id)],
        ];
        $rules = $existing ? [...$base, 'role' => 'prohibited', 'outlet_id' => 'prohibited', 'joined_on' => 'prohibited', 'password' => 'prohibited'] : [
            ...$base, 'role' => 'required|in:employee,supervisor,admin', 'password' => 'required|string|min:12|max:72',
            'outlet_id' => 'required_if:role,employee|prohibited_unless:role,employee|integer|exists:outlets,id',
            'joined_on' => 'required_if:role,employee|prohibited_unless:role,employee|date_format:Y-m-d',
        ];
        $employee = ($existing?->role ?? $request->input('role')) === 'employee';
        $rules['position_id'] = $employee ? 'required|integer|exists:positions,id' : 'prohibited';
        $data = $request->validate($rules);
        DB::transaction(function () use ($existing, $data, $actor) {
            $outletId = $existing?->outlet_id ?? $data['outlet_id'] ?? null;
            $outlet = $outletId ? DB::table('outlets')->lockForUpdate()->find($outletId) : null;
            if ($outlet && ! $outlet->active) {
                throw new DomainException('Outlet harus aktif.');
            }
            if (isset($data['position_id']) && ! DB::table('positions')->where('active', true)->lockForUpdate()->find($data['position_id'])) {
                throw new DomainException('Pilih posisi aktif.');
            }
            if (! $existing) {
                $id = DB::table('users')->insertGetId([...$data, 'password' => Hash::make($data['password']), 'created_at' => now(), 'updated_at' => now()]);
                Activity::audit('account_created', $actor->id, details: ['user_id' => $id]);

                return;
            }
            $existing = DB::table('users')->lockForUpdate()->find($existing->id);
            if (isset($data['position_id']) && $existing->position_id !== (int) $data['position_id'] && $this->futureSchedule($outletId, $existing->id)) {
                throw new DomainException('Selesaikan jadwal mendatang sebelum mengganti posisi.');
            }
            DB::table('users')->where('id', $existing->id)->update([...$data, 'updated_at' => now()]);
            Activity::audit('account_updated', $actor->id, details: ['user_id' => $existing->id]);
        }, 3);

        return back()->with('message', 'Akun tersimpan.');
    }

    public function assign(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor->role === 'admin', 403);
        $data = $request->validate(['outlet_id' => 'required|integer|exists:outlets,id', 'user_id' => 'required|integer|exists:users,id']);
        DB::transaction(function () use ($data, $actor) {
            $outlet = DB::table('outlets')->lockForUpdate()->find($data['outlet_id']);
            $user = DB::table('users')->lockForUpdate()->find($data['user_id']);
            if (! $outlet->active || $user->disabled_at || $user->role !== 'supervisor') {
                throw new DomainException('Pilih outlet aktif dan akun SPV aktif.');
            }
            $current = DB::table('supervisor_assignments')->where('outlet_id', $outlet->id)->first();
            if ($current?->user_id === $user->id) {
                return;
            }
            if (DB::table('supervisor_assignments')->where('user_id', $user->id)->exists() || OutletAccess::supervisor($outlet->id)) {
                throw new DomainException('SPV atau outlet sudah memiliki penugasan aktif.');
            }
            DB::table('supervisor_assignments')->updateOrInsert(['outlet_id' => $outlet->id], ['user_id' => $user->id]);
            Activity::audit('supervisor_assigned', $actor->id, details: $data);
        }, 3);

        return back()->with('message', 'SPV ditugaskan.');
    }

    public function accountAction(Request $request, int $id, string $action): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor->role === 'admin', 403);
        $data = $action === 'reset' ? $request->validate(['password' => 'required|string|min:12|max:72']) : [];
        $notice = DB::transaction(function () use ($id, $action, $data, $actor) {
            $target = DB::table('users')->find($id);
            abort_unless($target, 404);
            $outletId = $target->outlet_id ?? DB::table('supervisor_assignments')->where('user_id', $id)->value('outlet_id');
            if ($outletId) {
                DB::table('outlets')->lockForUpdate()->find($outletId);
            }
            if ($target->role === 'admin' && $action === 'disable') {
                $admins = DB::table('users')->where('role', 'admin')->whereNull('disabled_at')->orderBy('id')->lockForUpdate()->pluck('id');
                if ($admins->count() === 1 && $admins->contains($id)) {
                    throw new DomainException('Admin aktif terakhir tidak dapat dinonaktifkan.');
                }
            }
            $target = DB::table('users')->lockForUpdate()->find($id);
            if ($target->disabled_at) {
                throw new DomainException('Akun sudah nonaktif.');
            }

            $fields = $action === 'reset' ? ['password' => Hash::make($data['password']), 'must_change_password' => true] : ['disabled_at' => now()];
            DB::table('users')->where('id', $id)->increment('session_version', 1, [...$fields, 'updated_at' => now()]);
            DB::table('sessions')->where('user_id', $id)->delete();
            if ($action === 'disable' && $target->role === 'employee') {
                $pending = DB::table('swaps')->where('outlet_id', $outletId)->where(fn ($query) => $query->where('requester_id', $id)->orWhere('partner_id', $id))
                    ->whereIn('id', DB::table('schedule_reservations')->select('swap_id'))->pluck('id')->all();
                SwapService::invalidate($pending, 'Akun karyawan dinonaktifkan.');
                if ($this->futureSchedule($outletId, $id)) {
                    Activity::notify([OutletAccess::supervisor($outletId)], 'Karyawan dinonaktifkan. Periksa shift yang perlu pengganti.', '/jadwal');
                }
            }
            Activity::audit('account_'.$action, $actor->id, details: ['user_id' => $id]);

            return match (true) {
                $action === 'reset' => 'Password sementara telah diatur dan sesi lama dicabut.',
                $target->role === 'supervisor' && $outletId => 'Akun nonaktif. Outlet perlu pengganti SPV.',
                default => 'Akun dan sesi lama telah dinonaktifkan.',
            };
        }, 3);

        return back()->with('message', $notice);
    }

    private function futureSchedule(int $outlet, ?int $user = null): bool
    {
        $zone = DB::table('outlets')->where('id', $outlet)->value('timezone');
        $today = CarbonImmutable::now($zone)->toDateString();

        return DB::table('schedule_days as d')->join('schedule_weeks as w', 'd.schedule_week_id', '=', 'w.id')
            ->where('w.outlet_id', $outlet)->where('d.work_date', '>=', $today)
            ->when($user, fn ($query) => $query->where('d.user_id', $user))->exists();
    }
}
