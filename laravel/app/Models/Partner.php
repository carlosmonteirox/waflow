<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Partner extends Model
{
    use HasUuid;

    public const REFERRAL_CODE_LENGTH = 6;

    protected $fillable = [
        'uuid', 'owner_id', 'name', 'slug', 'status', 'brand_name', 'primary_color',
        'custom_domain', 'commission_percent', 'referral_code',
    ];

    protected function casts(): array
    {
        return ['commission_percent' => 'decimal:2'];
    }

    public static function generateReferralCode(): string
    {
        do {
            $code = Str::upper(Str::random(self::REFERRAL_CODE_LENGTH));
        } while (self::query()->where('referral_code', $code)->exists());

        return $code;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(PartnerMember::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'partner_members')
            ->withPivot(['role', 'status', 'joined_at'])
            ->withTimestamps();
    }

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'partner_workspaces')
            ->withPivot(['status', 'commission_percent', 'referred_at'])
            ->withTimestamps();
    }
}
