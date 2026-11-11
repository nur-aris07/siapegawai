<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuickQuizQuotaUsage extends Model
{
    const UPDATED_AT = null;
    protected $table = 'qq_quota_usages';
    protected $fillable = ['user_id','qq_quota_policy_id','bucket_date_wib','idempotency_key','attempt_ref','limit_snapshot','unlimited_snapshot','created_at'];
    protected function casts(): array { return ['unlimited_snapshot'=>'boolean','created_at'=>'datetime','limit_snapshot'=>'integer']; }
}
