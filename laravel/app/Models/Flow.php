<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Flow extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'workspace_id',
        'name',
        'trigger_type',
        'status',
        'steps',
        'last_run_at',
        'run_count',
        'failure_count',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'last_run_at' => 'datetime',
            'run_count' => 'integer',
            'failure_count' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function executions(): HasMany
    {
        return $this->hasMany(FlowExecution::class);
    }
}
