<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManualConfirmation extends Model
{
    protected $fillable = [
        'registration_request_id', 'user_id', 'context_type', 'confirmation_type',
        'external_reference', 'decision', 'note', 'confirmed_by', 'confirmed_at',
    ];
    protected function casts(): array { return ['confirmed_at' => 'datetime']; }
}
