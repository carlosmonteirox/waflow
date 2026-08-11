<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'monthly_price_cents',
        'instance_limit',
        'message_limit',
        'webhook_limit',
        'api_key_limit',
        'portal_limit',
        'features',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'monthly_price_cents' => 'integer',
            'instance_limit' => 'integer',
            'message_limit' => 'integer',
            'webhook_limit' => 'integer',
            'api_key_limit' => 'integer',
            'portal_limit' => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
