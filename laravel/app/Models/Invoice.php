<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid', 'workspace_id', 'subscription_id', 'number', 'status', 'amount_cents', 'currency',
        'payment_method', 'provider', 'provider_reference', 'payment_url', 'pix_code', 'due_at',
        'paid_at', 'metadata',
    ];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'due_at' => 'datetime', 'paid_at' => 'datetime', 'metadata' => 'array'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
