<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrialIdentifier extends Model
{
    protected $fillable = ['workspace_id', 'type', 'value_hash'];
}
