<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;
    protected $fillable = ['actor_id', 'action', 'subject_type', 'subject_id', 'before_summary', 'after_summary', 'created_at'];
    protected function casts(): array { return ['before_summary' => 'array', 'after_summary' => 'array', 'created_at' => 'datetime']; }
}
