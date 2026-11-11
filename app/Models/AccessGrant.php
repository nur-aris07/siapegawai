<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessGrant extends Model
{
    protected $fillable = ['user_id', 'grant_type', 'resource_type', 'resource_id', 'starts_at', 'ends_at', 'status', 'granted_by', 'revoke_reason'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function qqQuotaPolicy(): BelongsTo { return $this->belongsTo(QuickQuizQuotaPolicy::class, 'resource_id'); }

    protected function casts(): array { return ['starts_at' => 'datetime', 'ends_at' => 'datetime']; }
}
