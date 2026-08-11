<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrialRegistration extends Model
{
    protected $fillable = ['user_id', 'workspace_id', 'email_hash', 'email_domain', 'ip_hash', 'fingerprint_hash', 'site_domain', 'status', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
