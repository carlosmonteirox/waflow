<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Webhook extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'workspace_id',
        'name',
        'url',
        'events',
        'status',
        'signing_secret',
        'last_delivery_status',
        'last_delivered_at',
        'failure_count',
    ];

    protected $hidden = [
        'signing_secret',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'signing_secret' => 'encrypted',
            'last_delivered_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
