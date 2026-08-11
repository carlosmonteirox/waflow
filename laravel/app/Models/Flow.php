<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'last_run_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
