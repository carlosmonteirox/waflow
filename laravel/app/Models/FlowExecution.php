<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlowExecution extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid', 'workspace_id', 'flow_id', 'source_key', 'trigger_type', 'status',
        'trigger_payload', 'result', 'error', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'trigger_payload' => 'array', 'result' => 'array',
            'started_at' => 'datetime', 'completed_at' => 'datetime',
        ];
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(Flow::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
