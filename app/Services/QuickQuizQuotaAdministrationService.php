<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\MemberPlan;
use App\Models\QuickQuizQuotaPolicy;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Configure QQ quota per BUSINESS package. No user-specific QQ grant exists here. */
class QuickQuizQuotaAdministrationService
{
    public function savePlanPolicy(User $actor, MemberPlan $plan, array $input): QuickQuizQuotaPolicy
    {
        return DB::transaction(function () use ($actor, $plan, $input): QuickQuizQuotaPolicy {
            if ($actor->role !== 'superadmin' || $actor->status !== 'active') abort(403);
            $plan = MemberPlan::query()->findOrFail($plan->id);
            $unlimited = (bool) ($input['unlimited'] ?? false);
            $limit = $unlimited ? null : ($input['daily_limit'] ?? null);
            if ($unlimited && ($input['daily_limit'] ?? null) !== null && ($input['daily_limit'] ?? '') !== '') {
                throw ValidationException::withMessages(['daily_limit' => 'Kosongkan angka saat memilih unlimited.']);
            }
            if ($input['status'] === 'active' && !$unlimited && $limit === null) {
                throw ValidationException::withMessages(['daily_limit' => 'Kuota paket aktif wajib disahkan dengan angka atau unlimited eksplisit.']);
            }
            $starts = $input['starts_at'] ?? null;
            $ends = $input['ends_at'] ?? null;
            if ($starts && $ends && CarbonImmutable::parse($ends)->lessThanOrEqualTo(CarbonImmutable::parse($starts))) {
                throw ValidationException::withMessages(['ends_at' => 'Tanggal berakhir harus sesudah tanggal mulai.']);
            }
            $policy = QuickQuizQuotaPolicy::query()->where('member_plan_id', $plan->id)
                ->lockForUpdate()->first();
            $values = [
                'member_plan_id' => $plan->id, 'code' => 'PLAN_'.$plan->code,
                'name' => 'Kuota '.$plan->name, 'scope' => 'plan',
                'daily_limit' => $limit, 'unlimited' => $unlimited,
                'status' => $input['status'], 'starts_at' => $starts, 'ends_at' => $ends,
                'approved_by' => $input['status'] === 'active' ? $actor->id : null,
            ];
            $before = $policy?->only(array_keys($values)) ?? [];
            if ($policy) $policy->update($values);
            else $policy = QuickQuizQuotaPolicy::query()->create($values);
            AuditLog::query()->create([
                'actor_id' => $actor->id,
                'action' => $before ? 'qq.plan_quota_update' : 'qq.plan_quota_create',
                'subject_type' => QuickQuizQuotaPolicy::class, 'subject_id' => $policy->id,
                'before_summary' => $before, 'after_summary' => $policy->only(array_keys($values)),
                'created_at' => now(),
            ]);
            return $policy;
        }, 3);
    }
}
