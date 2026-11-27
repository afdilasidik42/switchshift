<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class SessionController
{
    public function login(): Response
    {
        return Inertia::render('Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['employee_code' => 'required|string|max:32', 'password' => 'required|string|max:72']);
        $key = 'login:'.hash('sha256', strtolower($data['employee_code']).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['employee_code' => 'Terlalu banyak percobaan. Coba lagi dalam satu menit.']);
        }
        if (! Auth::attempt([...$data, 'disabled_at' => null])) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['employee_code' => 'ID karyawan atau password tidak cocok.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->session()->put('account_version', $request->user()->session_version);

        return redirect()->route($request->user()->must_change_password ? 'password.edit' : 'dashboard');
    }

    public function password(): Response
    {
        return Inertia::render('Password');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => 'required|string|current_password|max:72',
            'password' => 'required|string|confirmed|min:12|max:72|different:current_password',
        ]);
        $user = $request->user();
        $updated = DB::table('users')->where('id', $user->id)->whereNull('disabled_at')
            ->where('session_version', $user->session_version)->update([
                'password' => Hash::make($data['password']),
                'must_change_password' => false,
                'session_version' => $user->session_version + 1,
                'updated_at' => now(),
            ]);
        abort_unless($updated, 409, 'Akun telah diperbarui. Masuk kembali.');
        $user->refresh();
        $request->session()->regenerate();
        $request->session()->put('account_version', $user->session_version);

        return redirect()->route('dashboard')->with('message', 'Password sudah diperbarui.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
