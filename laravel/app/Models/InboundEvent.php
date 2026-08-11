<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InboundEvent extends Model
{
    protected $fillable = ['workspace_id', 'whatsapp_instance_id', 'provider', 'event', 'idempotency_key', 'delivery_id', 'payload', 'processed_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'processed_at' => 'datetime'];
    }
}
