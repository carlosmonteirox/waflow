<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Portal extends Model
{
    use HasUuid;

    protected $fillable = ['uuid', 'workspace_id', 'name', 'url', 'domain', 'status', 'verification_token', 'verified_at', 'metadata'];

    protected $hidden = ['verification_token'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime', 'metadata' => 'array'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
