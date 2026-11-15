<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageUpgradeRequest extends Model
{
    protected $fillable = ['reference','user_id','from_member_plan_id','target_member_plan_id','target_published_version_id','status','admin_reason','processed_by','processed_at'];
    protected function casts(): array { return ['processed_at' => 'datetime', 'user_id' => 'integer', 'from_member_plan_id' => 'integer', 'target_member_plan_id' => 'integer', 'target_published_version_id' => 'integer']; }
    public function participant(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function sourcePlan(): BelongsTo { return $this->belongsTo(MemberPlan::class, 'from_member_plan_id'); }
    public function targetPlan(): BelongsTo { return $this->belongsTo(MemberPlan::class, 'target_member_plan_id'); }
    public function targetVersion(): BelongsTo { return $this->belongsTo(MemberPlanVersion::class, 'target_published_version_id'); }
}
