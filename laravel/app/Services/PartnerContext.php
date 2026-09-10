<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\User;

class PartnerContext
{
    public function forUser(User $user): ?Partner
    {
        $selectedUuid = session()->get('waflow.partner_uuid');
        $query = $user->partners()
            ->where('partners.status', 'active')
            ->wherePivot('status', 'active');
        $partner = $selectedUuid ? (clone $query)->where('partners.uuid', $selectedUuid)->first() : null;
        $partner ??= $query->first();

        if ($partner) {
            session()->put('waflow.partner_uuid', $partner->uuid);
        }

        return $partner;
    }
}
