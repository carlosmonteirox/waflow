<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    protected $fillable = [
        'workspace_id',
        'name',
        'token_prefix',
        'token_hash',
        'abilities',
        'last_used_at',
        'expires_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return array{model: self, plain_text_token: string}
     */
    public static function issue(Workspace $workspace, string $name, array $abilities = ['*']): array
    {
        $plainTextToken = 'waflow_'.Str::random(48);

        return [
            'model' => self::query()->create([
                'workspace_id' => $workspace->id,
                'name' => $name,
                'token_prefix' => substr($plainTextToken, 0, 15),
                'token_hash' => hash('sha256', $plainTextToken),
                'abilities' => $abilities,
            ]),
            'plain_text_token' => $plainTextToken,
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
