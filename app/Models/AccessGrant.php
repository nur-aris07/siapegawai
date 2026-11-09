<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessGrant extends Model
{
    protected $fillable = ['user_id', 'grant_type', 'resource_type', 'resource_id', 'starts_at', 'ends_at', 'status', 'granted_by', 'revoke_reason'];

    protected function casts(): array { return ['starts_at' => 'datetime', 'ends_at' => 'datetime']; }
}
