<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Product packages, distinct from CAT tryout_packages. Prices/limits are NOT defined here. */
class MemberPlan extends Model
{
    protected $fillable = ['code', 'name', 'is_paid', 'tier'];

    protected function casts(): array
    {
        return ['is_paid' => 'boolean', 'tier' => 'integer'];
    }

    public function quizQuotaPolicy(): HasOne
    {
        return $this->hasOne(QuickQuizQuotaPolicy::class, 'member_plan_id');
    }
}
