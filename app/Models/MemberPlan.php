<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Product packages, distinct from CAT tryout_packages. Prices/limits are NOT defined here. */
class MemberPlan extends Model
{
    protected $fillable = ['code', 'name', 'is_paid', 'tier', 'availability', 'is_archived', 'published_version_id'];

    protected function casts(): array
    {
        return ['is_paid' => 'boolean', 'tier' => 'integer', 'is_archived' => 'boolean', 'published_version_id' => 'integer'];
    }

    public function publishedVersion(): BelongsTo { return $this->belongsTo(MemberPlanVersion::class, 'published_version_id'); }

    public function versions(): HasMany { return $this->hasMany(MemberPlanVersion::class, 'member_plan_id'); }

    public function quizQuotaPolicy(): HasOne
    {
        return $this->hasOne(QuickQuizQuotaPolicy::class, 'member_plan_id');
    }
}
