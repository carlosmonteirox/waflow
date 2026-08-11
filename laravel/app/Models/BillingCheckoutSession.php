<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingCheckoutSession extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid', 'workspace_id', 'plan_id', 'created_by', 'payment_method', 'status', 'provider',
        'provider_reference', 'amount_cents', 'currency', 'checkout_url', 'provider_payload',
        'expires_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer', 'provider_payload' => 'array',
            'expires_at' => 'datetime', 'completed_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
