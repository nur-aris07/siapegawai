<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberPlanVersion extends Model
{
    protected $fillable = ['member_plan_id','version_no','title','summary','benefits','status','created_by','published_at'];
    protected function casts(): array { return ['benefits' => 'array','published_at' => 'datetime','version_no' => 'integer']; }
    public function plan(): BelongsTo { return $this->belongsTo(MemberPlan::class, 'member_plan_id'); }
}
