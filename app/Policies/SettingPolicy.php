<?php

namespace App\Policies;

use App\Models\Setting;
use App\Models\User;

class SettingPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole(User::ROLE_SUPER_ADMIN)) {
            return true;
        }
        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('setting.viewAny');
    }

    public function view(User $user, Setting $setting): bool
    {
        return $user->hasPermission('setting.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('setting.create');
    }

    public function update(User $user, Setting $setting): bool
    {
        return $user->hasPermission('setting.update');
    }

    public function delete(User $user, Setting $setting): bool
    {
        return $user->hasPermission('setting.delete');
    }

    public function restore(User $user, Setting $setting): bool
    {
        return $user->hasPermission('setting.restore');
    }

    public function forceDelete(User $user, Setting $setting): bool
    {
        return false;
    }
}