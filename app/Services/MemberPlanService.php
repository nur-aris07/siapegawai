<?php

namespace App\Services;

use App\Models\AccessGrant;
use App\Models\AuditLog;
use App\Models\MemberPlan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Package membership belongs to the participant, NOT to an individual Quiz Kilat. */
class MemberPlanService
{
    /** @return array{plan:MemberPlan,active_membership:?AccessGrant} */
    public function current(User $user, ?CarbonImmutable $instant = null, bool $lock = false): array
    {
        $now = ($instant ?? CarbonImmutable::now('UTC'))->setTimezone('UTC');
        $query = AccessGrant::query()->where('user_id', $user->id)
            ->where('grant_type', 'member_plan')->where('resource_type', 'member_plan')
            ->where('status', 'active')->where('starts_at', '<=', $now)
            ->where(fn($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now));
        if ($lock) $query->lockForUpdate();
        $memberships = $query->orderByDesc('id')->get();
        $plans = MemberPlan::query()->whereIn('id', $memberships->pluck('resource_id')->all())
            ->where('is_paid', true)->with('quizQuotaPolicy')->get()->keyBy('id');
        $eligible = $memberships->filter(fn($membership) => $plans->has((int) $membership->resource_id));
        // In an accidental/legacy overlap, take the highest currently approved quota,
        // never sum. Normal package attribution keeps exactly one paid membership.
        $rank = function ($membership) use ($plans, $now): array {
            $plan = $plans[(int) $membership->resource_id];
            $policy = $plan->quizQuotaPolicy;
            $valid = $policy && $policy->scope === 'plan' && $policy->status === 'active'
                && (!$policy->starts_at || !$policy->starts_at->greaterThan($now))
                && (!$policy->ends_at || $policy->ends_at->greaterThan($now))
                && ($policy->unlimited || $policy->daily_limit !== null);
            return [$valid ? 1 : 0, $valid && $policy->unlimited ? 1 : 0,
                $valid ? (int) ($policy->daily_limit ?? 0) : -1, $plan->tier];
        };
        $matched = $eligible->sort(function ($a, $b) use ($rank): int {
            $aRank = $rank($a); $bRank = $rank($b);
            for ($i = 0; $i < count($aRank); $i++) {
                if ($aRank[$i] !== $bRank[$i]) return $bRank[$i] <=> $aRank[$i];
            }
            return $b->id <=> $a->id;
        })->first();
        if ($matched) return ['plan' => $plans[(int) $matched->resource_id], 'active_membership' => $matched];
        return ['plan' => MemberPlan::query()->where('code', 'PEJUANG')->firstOrFail(), 'active_membership' => null];
    }

    /**
     * Temporary manual package attribution after existing external confirmation,
     * until a commerce/payment workflow integrates with this service. NOT a QQ grant.
     * Changing the package never resets QQ daily usage or CAT data.
     */
    public function setPlan(User $actor, User $participant, MemberPlan $newPlan, string $reason, ?string $endsAt = null): void
    {
        DB::transaction(function () use ($actor, $participant, $newPlan, $reason, $endsAt): void {
            if ($actor->role !== 'superadmin' || $actor->status !== 'active') abort(403);
            $participant = User::query()->lockForUpdate()->findOrFail($participant->id);
            if ($participant->role !== 'participant' || $participant->status !== 'active') {
                throw ValidationException::withMessages(['member_plan_id' => 'Paket hanya dapat ditetapkan untuk peserta aktif.']);
            }
            $newPlan = MemberPlan::query()->findOrFail($newPlan->id);
            $before = $this->current($participant, null, true);
            $now = now();
            $existing = AccessGrant::query()->where('user_id', $participant->id)
                ->where('grant_type', 'member_plan')->where('resource_type', 'member_plan')
                ->where('status', 'active')->lockForUpdate()->get();
            // Double-submit of the same package + expiry must not churn membership history.
            $oldExpiry = $before['active_membership']?->ends_at?->timestamp;
            $newExpiry = $endsAt ? CarbonImmutable::parse($endsAt, 'UTC')->timestamp : null;
            if ($before['plan']->id === $newPlan->id && $existing->count() <= 1
                && (!$newPlan->is_paid || $oldExpiry === $newExpiry)) return;
            // Revoke all active package memberships (including any overlaps). Preserve history.
            foreach ($existing as $membership) {
                $membership->update(['status' => 'revoked', 'revoke_reason' => $reason]);
            }
            if ($newPlan->is_paid) {
                $ends = $endsAt ? CarbonImmutable::parse($endsAt, 'UTC') : null;
                if ($ends && $ends->lessThanOrEqualTo($now)) {
                    throw ValidationException::withMessages(['ends_at' => 'Waktu kedaluwarsa harus di masa depan.']);
                }
                AccessGrant::query()->create([
                    'user_id' => $participant->id, 'grant_type' => 'member_plan',
                    'resource_type' => 'member_plan', 'resource_id' => $newPlan->id,
                    'starts_at' => $now, 'ends_at' => $endsAt, 'status' => 'active',
                    'granted_by' => $actor->id,
                ]);
            }
            AuditLog::query()->create([
                'actor_id' => $actor->id, 'action' => 'participant.member_plan_changed',
                'subject_type' => 'user', 'subject_id' => $participant->id,
                'before_summary' => ['plan' => $before['plan']->code],
                'after_summary' => ['plan' => $newPlan->code, 'reason' => $reason, 'ends_at' => $endsAt],
                'created_at' => $now,
            ]);
        }, 3);
    }
}
