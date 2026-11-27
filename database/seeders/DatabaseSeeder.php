<?php

namespace Database\Seeders;

use App\Services\Scheduling\ScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('SEED_TEMP_PASSWORD');
        if (! is_string($password) || strlen($password) < 12) {
            throw new RuntimeException('Isi SEED_TEMP_PASSWORD minimal 12 karakter untuk seed simulasi.');
        }

        DB::transaction(function () use ($password) {
            $zone = 'Asia/Jakarta';
            $week = CarbonImmutable::now($zone)->startOfWeek()->addWeek();
            $outlet = DB::table('outlets')->insertGetId(['code' => 'SIM-01', 'name' => 'Outlet Simulasi 01', 'timezone' => $zone, 'active' => true]);
            $positions = DB::table('positions')->pluck('id', 'code');
            DB::table('users')->insert($this->user('ADMIN-SIM', 'Admin Simulasi', 'admin', $password));
            $supervisor = DB::table('users')->insertGetId($this->user('SPV-SIM', 'SPV Simulasi', 'supervisor', $password));
            DB::table('supervisor_assignments')->insert(['outlet_id' => $outlet, 'user_id' => $supervisor]);
            $people = [];
            foreach (['KASIR' => 4, 'BAKER' => 8] as $position => $count) {
                for ($index = 0; $index < $count; $index++) {
                    $number = $index + 1;
                    $people[] = [
                        'id' => DB::table('users')->insertGetId([
                            ...$this->user("{$position}-SIM-{$number}", ucfirst(strtolower($position))." Simulasi {$number}", 'employee', $password),
                            'outlet_id' => $outlet, 'position_id' => $positions[$position],
                            'joined_on' => $week->subWeeks(4)->toDateString(),
                        ]),
                        'position_id' => $positions[$position], 'position' => $position, 'index' => $index,
                    ];
                }
            }

            $weekId = DB::table('schedule_weeks')->insertGetId(['outlet_id' => $outlet, 'monday' => $week->toDateString()]);
            for ($offset = 0; $offset < 7; $offset++) {
                foreach (['KASIR' => 1, 'BAKER' => 2] as $position => $morningRequired) {
                    $working = array_values(array_filter($people, fn ($person) => $person['position'] === $position
                        && ! in_array($offset, [$person['index'] % 7, ($person['index'] + 3) % 7], true)));
                    foreach ($people as $person) {
                        if ($person['position'] !== $position) {
                            continue;
                        }
                        $order = array_search($person, $working, true);
                        $shift = $order === false ? 'libur' : ($order < $morningRequired ? 'pagi' : 'sore');
                        DB::table('schedule_days')->insert([
                            'schedule_week_id' => $weekId, 'user_id' => $person['id'], 'position_id' => $person['position_id'],
                            'work_date' => $week->addDays($offset)->toDateString(), 'shift' => $shift,
                        ]);
                    }
                }
            }
            $service = app(ScheduleService::class);
            $outletRecord = DB::table('outlets')->find($outlet);
            $service->validateChanges($outletRecord, [], [$week->toDateString()]);
            $changes = DB::table('schedule_days as d')->join('users as u', 'u.id', '=', 'd.user_id')
                ->join('positions as p', 'p.id', '=', 'd.position_id')->where('d.schedule_week_id', $weekId)
                ->get(['d.user_id', 'd.work_date', 'd.shift', 'u.name', 'p.name as position'])
                ->map(fn ($day) => ['user_id' => $day->user_id, 'name' => $day->name,
                    'date' => $day->work_date, 'before' => null, 'after' => $day->shift,
                    'position' => $day->position])->all();
            $service->record($outletRecord, $supervisor, 'penerbitan', 'Jadwal simulasi awal', $changes);
        });
    }

    private function user(string $code, string $name, string $role, string $password): array
    {
        return ['employee_code' => $code, 'name' => $name, 'role' => $role, 'password' => Hash::make($password),
            'must_change_password' => true, 'created_at' => now(), 'updated_at' => now()];
    }
}
