<?php

namespace App\Support;

use App\Models\User;

final class AdminAccess
{
    public static function allows(string $permission): bool
    {
        /** @var User|null $user */
        $user = auth('web')->user();

        return $user?->hasPermission($permission) ?? false;
    }
}
