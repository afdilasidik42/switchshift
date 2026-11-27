<?php

use App\Services\Swaps\SwapService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;

Artisan::command('rotio:admin', function () {
    $id = $this->ask('ID admin');
    $name = $this->ask('Nama admin');
    $password = $this->secret('Password sementara (minimal 12 karakter)', false);
    $data = Validator::make(['employee_code' => $id, 'name' => $name, 'password' => $password], [
        'employee_code' => 'required|alpha_dash:ascii|max:32|unique:users',
        'name' => 'required|string|max:120',
        'password' => 'required|string|min:12|max:72',
    ])->validate();
    DB::table('users')->insert([...$data, 'password' => Hash::make($password), 'role' => 'admin', 'created_at' => now(), 'updated_at' => now()]);
    $this->info('Admin dibuat. Ganti password sementara saat pertama masuk.');
})->purpose('Buat akun admin lokal dengan prompt password tersembunyi');

Artisan::command('rotio:expire', function (SwapService $service) {
    DB::table('outlets')->select('id')->chunkById(100, function ($outlets) use ($service) {
        foreach ($outlets as $outlet) {
            DB::transaction(function () use ($outlet, $service) {
                DB::table('outlets')->lockForUpdate()->find($outlet->id);
                $service->expire($outlet->id);
            }, 3);
        }
    });
})->purpose('Tutup pengajuan dan pembatalan yang melewati tenggat');

Schedule::command('rotio:expire')->everyMinute()->withoutOverlapping();
