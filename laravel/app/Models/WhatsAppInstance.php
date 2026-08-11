<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppInstance extends Model
{
    use HasUuid;

    protected $table = 'whatsapp_instances';

    protected $fillable = [
        'uuid',
        'workspace_id',
        'name',
        'session_name',
        'openwa_session_id',
        'phone_number',
        'status',
        'engine_payload',
        'last_seen_at',
        'connected_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'engine_payload' => 'array',
            'last_seen_at' => 'datetime',
            'connected_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function messageEvents(): HasMany
    {
        return $this->hasMany(MessageEvent::class);
    }
}
