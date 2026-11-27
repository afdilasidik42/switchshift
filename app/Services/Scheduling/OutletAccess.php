<?php

namespace App\Services\Scheduling;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use stdClass;

final class OutletAccess
{
    public static function resolve(User $user): ?stdClass
    {
        $id = match ($user->role) {
            'employee' => $user->outlet_id,
            'supervisor' => DB::table('supervisor_assignments')->where('user_id', $user->id)->value('outlet_id'),
            default => null,
        };

        return $id ? DB::table('outlets')->where('active', true)->find($id) : null;
    }

    public static function supervisor(int $outlet): ?int
    {
        return DB::table('supervisor_assignments as a')->join('users as u', 'a.user_id', '=', 'u.id')
            ->where('a.outlet_id', $outlet)->whereNull('u.disabled_at')->where('u.role', 'supervisor')->value('u.id');
    }

    public static function require(User $user, ?string $role = null): stdClass
    {
        abort_if($role && $user->role !== $role, 403);
        $outlet = self::resolve($user);
        abort_unless($outlet, 403, 'Akun belum memiliki outlet aktif.');

        return $outlet;
    }
}
