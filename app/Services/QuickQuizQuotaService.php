<?php

namespace App\Services;

use App\Models\QuickQuizDailyQuotaCounter;
use App\Models\QuickQuizQuotaPolicy;
use App\Models\QuickQuizQuotaUsage;
use App\Models\MemberPlan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * PKG-OPS-ENT-MIN, isolated from CAT and Class Practice.
 * No public participant routes; only QQ-CORE-02 may call startWithQuota() when
 * it can create an actual attempt AND issue its first question transactionally.
 */
class QuickQuizQuotaService
{
    public function __construct(private readonly MemberPlanService $memberPlans) {}

    public function dateWib(?CarbonImmutable $instant = null): string
    {
        return ($instant ?? CarbonImmutable::now('UTC'))->setTimezone('Asia/Jakarta')->toDateString();
    }

    /** Eligible benefit comes from the participant's current member plan, not a QQ grant. */
    public function resolve(User $user, ?CarbonImmutable $instant = null, bool $lock = false): array
    {
        $now = ($instant ?? CarbonImmutable::now('UTC'))->setTimezone('UTC');
        $denied = ['allowed' => false, 'reason' => 'POLICY_NOT_CONFIGURED',
            'policy_id' => null, 'daily_limit' => null, 'unlimited' => false,
            'bucket_date_wib' => $this->dateWib($now),
            'plan_code' => null, 'plan_name' => null];
        if ($user->role !== 'participant' || $user->status !== 'active') {
            return array_replace($denied, ['reason' => 'ACCOUNT_INACTIVE']);
        }
        $ownership = $this->memberPlans->current($user, $now, $lock);
        $plan = $ownership['plan'];
        $denied['plan_code'] = $plan->code;
        $denied['plan_name'] = $plan->name;
        $query = QuickQuizQuotaPolicy::query()
            ->where('member_plan_id', $plan->id)
            ->where('scope', 'plan')->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now));
        if ($lock) $query->lockForUpdate();
        $policy = $query->first();
        // Draft, missing, expired, or unlimited without explicit approval = FAIL CLOSED.
        if (!$policy || (!$policy->unlimited && $policy->daily_limit === null)
            || ($policy->unlimited && $policy->daily_limit !== null)) return $denied;
        return array_replace($denied, [
            'allowed' => true, 'reason' => 'OK', 'policy_id' => $policy->id,
            'daily_limit' => $policy->daily_limit, 'unlimited' => (bool) $policy->unlimited,
        ]);
    }

    /** Only read; never creates a counter or charges a visit to the catalogue. */
    public function remaining(User $user, ?CarbonImmutable $instant = null): array
    {
        $resolved=$this->resolve($user,$instant);
        $used=(int) QuickQuizDailyQuotaCounter::query()->where('user_id',$user->id)
            ->where('bucket_date_wib',$resolved['bucket_date_wib'])->value('consumed_count');
        return array_merge($resolved,['consumed'=>$used,
            'remaining'=>!$resolved['allowed'] ? null : ($resolved['unlimited'] ? null : max(0,$resolved['daily_limit']-$used))]);
    }

    /**
     * Transactional integration seam for QQ-CORE-02. The callback MUST persist
     * the attempt and first-question issuance within this SAME DB transaction
     * and return its unique persisted attempt_ref. Never call from a GET/view.
     *
     * @param Closure():string $createAttemptAndIssueFirst
     */
    public function startWithQuota(User $user, string $idempotencyKey, Closure $createAttemptAndIssueFirst, ?CarbonImmutable $instant = null): array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{12,80}$/D',$idempotencyKey)) {
            throw ValidationException::withMessages(['attempt'=>'Kunci permintaan tidak valid.']);
        }
        return DB::transaction(function () use ($user,$idempotencyKey,$createAttemptAndIssueFirst,$instant) {
            // The user row is a per-user mutex: two tabs cannot create two debits
            // across different quizzes with one remaining allowance.
            $lockedUser=User::query()->lockForUpdate()->findOrFail($user->id);
            if ($lockedUser->role!=='participant' || $lockedUser->status!=='active') {
                throw ValidationException::withMessages(['quota'=>'Akun peserta tidak aktif.']);
            }
            $existing=QuickQuizQuotaUsage::query()->where('user_id',$lockedUser->id)
                ->where('idempotency_key',$idempotencyKey)->first();
            if ($existing) return ['attempt_ref'=>$existing->attempt_ref,'usage_id'=>$existing->id,
                'bucket_date_wib'=>$existing->bucket_date_wib,'resumed'=>true];
            $now=($instant ?? CarbonImmutable::now('UTC'))->setTimezone('UTC');
            $policy=$this->resolve($lockedUser,$now,true);
            if (!$policy['allowed']) throw ValidationException::withMessages(['quota'=>'Manfaat kuota Quiz Kilat belum dikonfigurasi atau tidak berlaku.']);
            $bucket=QuickQuizDailyQuotaCounter::query()->firstOrCreate(
                ['user_id'=>$lockedUser->id,'bucket_date_wib'=>$policy['bucket_date_wib']],
                ['consumed_count'=>0]);
            $bucket=QuickQuizDailyQuotaCounter::query()->lockForUpdate()->findOrFail($bucket->id);
            if (!$policy['unlimited'] && $bucket->consumed_count >= $policy['daily_limit']) {
                throw ValidationException::withMessages(['quota'=>'Kuota harian Quiz Kilat telah habis.']);
            }
            $attemptRef=$createAttemptAndIssueFirst();
            if (!is_string($attemptRef) || !preg_match('/^[A-Za-z0-9:_-]{1,100}$/D',$attemptRef)) {
                throw new \LogicException('Callback start wajib mengembalikan referensi attempt tersimpan yang valid.');
            }
            $usage=QuickQuizQuotaUsage::query()->create([
                'user_id'=>$lockedUser->id,'qq_quota_policy_id'=>$policy['policy_id'],
                'bucket_date_wib'=>$policy['bucket_date_wib'],'idempotency_key'=>$idempotencyKey,
                'attempt_ref'=>$attemptRef,'limit_snapshot'=>$policy['daily_limit'],
                'unlimited_snapshot'=>$policy['unlimited'],'created_at'=>$now,
            ]);
            $bucket->increment('consumed_count');
            return ['attempt_ref'=>$usage->attempt_ref,'usage_id'=>$usage->id,
                'bucket_date_wib'=>$policy['bucket_date_wib'],'resumed'=>false];
        }, 3);
    }
}
