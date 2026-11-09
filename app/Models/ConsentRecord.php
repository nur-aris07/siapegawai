<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsentRecord extends Model
{
    protected $fillable = ['user_id', 'policy_version', 'agreed_at', 'source'];

    protected function casts(): array { return ['agreed_at' => 'datetime']; }
}
