<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid', 'workspace_id', 'invoice_id', 'billing_checkout_session_id', 'payment_method',
        'status', 'amount_cents', 'currency', 'provider', 'provider_reference', 'failure_code',
        'failure_message', 'paid_at', 'metadata',
    ];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'paid_at' => 'datetime', 'metadata' => 'array'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(BillingCheckoutSession::class, 'billing_checkout_session_id');
    }
}
