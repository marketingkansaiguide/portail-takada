<?php

namespace App\Policies;

use App\Models\SeoSite;
use App\Models\User;

class SeoSitePolicy
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
        return $user->hasPermission('seo_site.viewAny');
    }

    public function view(User $user, SeoSite $site): bool
    {
        return $user->hasPermission('seo_site.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('seo_site.create');
    }

    public function update(User $user, SeoSite $site): bool
    {
        return $user->hasPermission('seo_site.update');
    }

    public function delete(User $user, SeoSite $site): bool
    {
        return $user->hasPermission('seo_site.delete');
    }

    public function restore(User $user, SeoSite $site): bool
    {
        return $user->hasPermission('seo_site.delete');
    }

    public function forceDelete(User $user, SeoSite $site): bool
    {
        return false;
    }
}
