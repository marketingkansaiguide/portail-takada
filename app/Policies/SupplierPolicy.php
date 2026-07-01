<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
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
        return $user->hasPermission('supplier.viewAny');
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->hasPermission('supplier.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('supplier.create');
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->hasPermission('supplier.update');
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->hasPermission('supplier.delete');
    }

    public function restore(User $user, Supplier $supplier): bool
    {
        return $user->hasPermission('supplier.restore');
    }

    public function forceDelete(User $user, Supplier $supplier): bool
    {
        return false;
    }
}