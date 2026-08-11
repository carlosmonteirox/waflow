<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiRequestLog extends Model
{
    protected $fillable = ['workspace_id', 'api_key_id', 'request_id', 'method', 'path', 'status_code', 'duration_ms', 'ip_hash'];
}
