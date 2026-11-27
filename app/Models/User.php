<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

final class User extends Authenticatable
{
    protected $hidden = ['password', 'remember_token', 'session_version'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'disabled_at' => 'immutable_datetime',
        ];
    }
}
