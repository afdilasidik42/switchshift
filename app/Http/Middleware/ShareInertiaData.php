<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

final class ShareInertiaData extends Middleware
{
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'account' => $user?->only(['id', 'employee_code', 'name', 'role', 'must_change_password']),
            'message' => fn () => $request->session()->get('message'),
            'unread' => fn () => $user ? DB::table('notifications')->where('user_id', $user->id)->whereNull('read_at')->count() : 0,
        ];
    }
}
