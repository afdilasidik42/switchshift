<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Scheduling\ScheduleService;
use App\Services\Swaps\SwapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class CoreFlowTest extends TestCase
{
    use RefreshDatabase;

    private string $testPassword;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testPassword = bin2hex(random_bytes(12));
    }

    private function account(string $role, ?int $outlet = null): User
    {
        return User::query()->forceCreate([
            'employee_code' => strtoupper($role).'-'.random_int(1000, 9999),
            'name' => 'Pengguna Simulasi', 'role' => $role, 'password' => Hash::make($this->testPassword),
            'outlet_id' => $outlet, 'joined_on' => $outlet ? '2026-11-23' : null,
            'must_change_password' => false, 'session_version' => 1,
        ]);
    }

    public function test_admin_cannot_edit_schedule_directly(): void
    {
        $admin = $this->account('admin');
        $this->actingAs($admin)->withSession(['account_version' => 1])
            ->post('/jadwal', ['week' => '2026-11-30', 'version' => 0, 'cells' => [1 => ['2026-11-30' => 'pagi']]])
            ->assertForbidden();
    }

    public function test_outlet_access_isolated_for_employee(): void
    {
        $first = DB::table('outlets')->insertGetId(['code' => 'ONE', 'name' => 'Satu', 'timezone' => 'Asia/Jakarta', 'active' => true]);
        $second = DB::table('outlets')->insertGetId(['code' => 'TWO', 'name' => 'Dua', 'timezone' => 'Asia/Jakarta', 'active' => true]);
        $employee = $this->account('employee', $first);
        $this->actingAs($employee)->withSession(['account_version' => 1])
            ->get('/jadwal')->assertOk()->assertInertia(fn ($page) => $page->component('Schedule')->where('outlet.id', $first));
        self::assertNotSame($first, $second);
    }

    public function test_disabled_session_loses_access(): void
    {
        $admin = $this->account('admin');
        $this->actingAs($admin)->withSession(['account_version' => 1])->get('/')->assertOk();
        $admin->forceFill(['disabled_at' => now(), 'session_version' => 2])->save();
        $this->get('/')->assertRedirect('/masuk');
    }

    public function test_login_and_forced_password_change(): void
    {
        $user = $this->account('admin');
        $user->forceFill(['must_change_password' => true])->save();
        $this->post('/masuk', ['employee_code' => $user->employee_code, 'password' => bin2hex(random_bytes(12))])
            ->assertSessionHasErrors('employee_code');
        $this->post('/masuk', ['employee_code' => $user->employee_code, 'password' => $this->testPassword])
            ->assertRedirect('/password');
        $this->get('/admin')->assertRedirect('/password');
        $newPassword = bin2hex(random_bytes(12));
        $this->post('/password', [
            'current_password' => $this->testPassword, 'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertRedirect('/');
        $this->get('/admin')->assertOk();
    }

    public function test_second_supervisor_assignment_is_rejected(): void
    {
        $admin = $this->account('admin');
        $first = $this->account('supervisor');
        $second = $this->account('supervisor');
        $outlet = DB::table('outlets')->insertGetId(['code' => 'ONE', 'name' => 'Satu', 'timezone' => 'Asia/Jakarta', 'active' => true]);
        $this->actingAs($admin)->withSession(['account_version' => 1])
            ->post('/admin/penugasan', ['outlet_id' => $outlet, 'user_id' => $first->id])->assertRedirect();
        $this->post('/admin/penugasan', ['outlet_id' => $outlet, 'user_id' => $second->id])->assertSessionHasErrors();
        $this->assertDatabaseHas('supervisor_assignments', ['outlet_id' => $outlet, 'user_id' => $first->id]);
    }

    public function test_supervisor_cannot_read_another_outlets_swap(): void
    {
        $firstOutlet = DB::table('outlets')->insertGetId(['code' => 'ONE', 'name' => 'Satu', 'timezone' => 'Asia/Jakarta', 'active' => true]);
        $secondOutlet = DB::table('outlets')->insertGetId(['code' => 'TWO', 'name' => 'Dua', 'timezone' => 'Asia/Jakarta', 'active' => true]);
        $supervisor = $this->account('supervisor');
        DB::table('supervisor_assignments')->insert(['outlet_id' => $firstOutlet, 'user_id' => $supervisor->id]);
        $position = DB::table('positions')->where('code', 'KASIR')->value('id');
        $first = $this->account('employee', $secondOutlet);
        $second = $this->account('employee', $secondOutlet);
        $swap = DB::table('swaps')->insertGetId([
            'outlet_id' => $secondOutlet, 'requester_id' => $first->id, 'partner_id' => $second->id,
            'position_id' => $position, 'status' => 'rejected', 'reason' => 'Alasan pribadi',
            'deadline' => now()->addDay(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($supervisor)->withSession(['account_version' => 1])
            ->get('/pertukaran/'.$swap)->assertNotFound();
    }

    public function test_peer_then_supervisor_approve_one_atomic_swap(): void
    {
        putenv('SEED_TEMP_PASSWORD='.bin2hex(random_bytes(12)));
        try {
            $this->seed();
        } finally {
            putenv('SEED_TEMP_PASSWORD');
        }
        $outlet = DB::table('outlets')->where('code', 'SIM-01')->first();
        $week = DB::table('schedule_weeks')->where('outlet_id', $outlet->id)->first();
        $days = DB::table('schedule_days as d')->join('users as u', 'u.id', '=', 'd.user_id')
            ->where('d.schedule_week_id', $week->id)->where('d.work_date', $week->monday)
            ->where('u.employee_code', 'like', 'KASIR-%')->where('d.shift', '!=', 'libur')->orderBy('d.shift')->get(['d.id', 'd.user_id', 'd.shift']);
        $first = $days->first();
        $second = $days->firstWhere('shift', 'sore');
        $requester = User::findOrFail($first->user_id);
        $partner = User::findOrFail($second->user_id);
        $supervisor = User::where('role', 'supervisor')->firstOrFail();
        $service = app(SwapService::class);
        $service->propose($requester, $first->id, $second->id, 'Pertukaran simulasi');
        $swap = DB::table('swaps')->first();
        self::assertSame('pending_peer', $swap->status);
        $service->act($partner, $swap->id, 'accept', null);
        self::assertSame('pending_supervisor', DB::table('swaps')->find($swap->id)->status);
        $service->act($supervisor, $swap->id, 'accept', null);
        self::assertSame('approved', DB::table('swaps')->find($swap->id)->status);
        self::assertSame($second->shift, DB::table('schedule_days')->find($first->id)->shift);
        self::assertSame($first->shift, DB::table('schedule_days')->find($second->id)->shift);
        self::assertSame(1, DB::table('schedule_changes')->where('swap_id', $swap->id)->count());

        $service->act($requester, $swap->id, 'request_cancellation', 'Kembali ke jadwal awal');
        self::assertSame($second->shift, DB::table('schedule_days')->find($first->id)->shift);
        $service->act($partner, $swap->id, 'cancellation_accept', null);
        $service->act($supervisor, $swap->id, 'cancellation_accept', null);
        self::assertSame('reversed', DB::table('swaps')->find($swap->id)->status);
        self::assertSame($first->shift, DB::table('schedule_days')->find($first->id)->shift);
        self::assertSame($second->shift, DB::table('schedule_days')->find($second->id)->shift);
    }

    public function test_stale_week_version_cannot_overwrite_schedule(): void
    {
        putenv('SEED_TEMP_PASSWORD='.bin2hex(random_bytes(12)));
        try {
            $this->seed();
        } finally {
            putenv('SEED_TEMP_PASSWORD');
        }
        $supervisor = User::where('role', 'supervisor')->firstOrFail();
        $week = DB::table('schedule_weeks')->first();
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Muat ulang');
        app(ScheduleService::class)->save($supervisor, $week->monday, 0, [], null, []);
    }
}
