<?php

namespace App\Services;

use App\Models\User;

class LoginDestination
{
    public function for(User $user): string
    {
        if ($user->is_platform_admin) {
            return route('admin.dashboard');
        }

        if ($user->partners()->where('partners.status', 'active')->wherePivot('status', 'active')->exists()) {
            return route('partner.dashboard');
        }

        return route('dashboard');
    }
}
