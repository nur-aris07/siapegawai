<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuickQuizQuotaPolicy extends Model
{
    protected $table = 'qq_quota_policies';
    protected $fillable = ['code','name','scope','daily_limit','unlimited','status','starts_at','ends_at','approved_by','member_plan_id'];
    public function memberPlan(): BelongsTo { return $this->belongsTo(MemberPlan::class, 'member_plan_id'); }
    protected function casts(): array
    {
        return ['daily_limit'=>'integer','unlimited'=>'boolean','starts_at'=>'datetime','ends_at'=>'datetime'];
    }
}
