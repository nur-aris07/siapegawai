<?php

namespace Tests\Feature;

use App\Models\AccessGrant;
use App\Models\AuditLog;
use App\Models\MemberPlan;
use App\Models\QuickQuizDailyQuotaCounter;
use App\Models\QuickQuizQuotaPolicy;
use App\Models\QuickQuizQuotaUsage;
use App\Models\User;
use App\Services\MemberPlanService;
use App\Services\QuickQuizQuotaService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SiapegawaiQuickQuizQuotaTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $role = 'participant', string $status = 'active'): User
    {
        return User::factory()->create(['role' => $role, 'status' => $status]);
    }

    private function plan(string $code): MemberPlan
    {
        return MemberPlan::query()->where('code', $code)->firstOrFail();
    }

    private function policy(string $code, ?int $limit, string $status = 'active', bool $unlimited = false): QuickQuizQuotaPolicy
    {
        $plan = $this->plan($code);
        return QuickQuizQuotaPolicy::query()->create([
            'member_plan_id' => $plan->id, 'code' => 'PLAN_'.$plan->code,
            'name' => 'Kuota '.$plan->name, 'scope' => 'plan',
            'daily_limit' => $unlimited ? null : $limit, 'unlimited' => $unlimited,
            'status' => $status,
        ]);
    }

    private function membership(User $user, string $code, string $status = 'active'): AccessGrant
    {
        return AccessGrant::query()->create([
            'user_id' => $user->id, 'grant_type' => 'member_plan',
            'resource_type' => 'member_plan', 'resource_id' => $this->plan($code)->id,
            'starts_at' => now()->subMinute(), 'status' => $status,
        ]);
    }

    public function test_only_three_product_plans_exist_without_automatic_quota_or_paid_membership(): void
    {
        $this->assertSame(['PEJUANG','ABDI_NEGARA','VIP'], MemberPlan::query()->orderBy('tier')->pluck('code')->all());
        $this->assertFalse($this->plan('PEJUANG')->is_paid);
        $this->assertTrue($this->plan('ABDI_NEGARA')->is_paid);
        $this->assertTrue($this->plan('VIP')->is_paid);
        $this->assertDatabaseCount('qq_quota_policies', 0);
        $this->assertDatabaseCount('access_grants', 0);
    }

    public function test_active_participant_implicitly_has_pejuang_without_qq_grant(): void
    {
        $member = $this->person();
        $policy = $this->policy('PEJUANG', 3);
        $service = app(QuickQuizQuotaService::class);
        $today = $service->remaining($member);
        $this->assertSame('PEJUANG', $today['plan_code']);
        $this->assertSame($policy->id, $today['policy_id']);
        $this->assertSame(3, $today['remaining']);
        $this->assertSame(0, $today['consumed']);
        $this->assertDatabaseCount('access_grants', 0);
        $this->assertDatabaseCount('qq_daily_quota_counters', 0);
    }

    public function test_no_valid_configured_quota_fails_closed_even_for_pejuang_and_vip(): void
    {
        $user = $this->person();
        $service = app(QuickQuizQuotaService::class);
        $this->assertSame('POLICY_NOT_CONFIGURED', $service->remaining($user)['reason']);
        $this->assertNull($service->remaining($user)['remaining']);
        $this->membership($user, 'VIP');
        $this->policy('PEJUANG', 3);
        $this->assertSame('VIP', $service->remaining($user)['plan_code']);
        $this->assertFalse($service->remaining($user)['allowed']);
        try { $service->startWithQuota($user, 'missing-plan-key-01', fn () => 'qq:missing'); $this->fail('Must deny'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('quota', $e->errors()); }
        $this->assertDatabaseCount('qq_quota_usages', 0);
    }

    public function test_paid_package_follows_user_and_outlives_individual_quiz_titles(): void
    {
        $user = $this->person();
        $this->policy('PEJUANG', 1);
        $this->policy('ABDI_NEGARA', 5);
        $this->membership($user, 'ABDI_NEGARA');
        $quota = app(QuickQuizQuotaService::class)->remaining($user);
        $this->assertSame('ABDI_NEGARA', $quota['plan_code']);
        $this->assertSame(5, $quota['remaining']);
        $this->assertDatabaseMissing('access_grants', ['user_id' => $user->id, 'grant_type' => 'qq_quota']);
    }

    public function test_expired_or_revoked_paid_membership_returns_to_pejuang(): void
    {
        $user = $this->person();
        $this->policy('PEJUANG', 2);
        $this->policy('VIP', 10);
        $old = $this->membership($user, 'VIP');
        $old->update(['ends_at' => now()->subSecond()]);
        $this->assertSame('PEJUANG', app(QuickQuizQuotaService::class)->resolve($user)['plan_code']);
        $old->update(['ends_at' => null, 'status' => 'revoked']);
        $this->assertSame(2, app(QuickQuizQuotaService::class)->remaining($user)['remaining']);
    }

    public function test_old_qq_grants_and_legacy_free_policy_do_not_override_package_membership(): void
    {
        $user = $this->person();
        $legacy = QuickQuizQuotaPolicy::query()->create([
            'code' => 'FREE', 'name' => 'Legacy', 'scope' => 'base', 'daily_limit' => 500,
            'status' => 'active', 'unlimited' => false,
        ]);
        AccessGrant::query()->create([
            'user_id' => $user->id, 'grant_type' => 'qq_quota',
            'resource_type' => 'qq_quota_policy', 'resource_id' => $legacy->id,
            'starts_at' => now()->subMinute(), 'status' => 'active',
        ]);
        $this->policy('PEJUANG', 2);
        $this->assertSame(2, app(QuickQuizQuotaService::class)->remaining($user)['daily_limit']);
        $this->assertDatabaseHas('access_grants', ['grant_type' => 'qq_quota', 'status' => 'active']);
    }

    public function test_explicit_zero_and_unlimited_are_distinct_from_missing_limit(): void
    {
        $user = $this->person();
        $this->policy('PEJUANG', 0);
        $service = app(QuickQuizQuotaService::class);
        $this->assertSame(0, $service->remaining($user)['remaining']);
        try { $service->startWithQuota($user, 'zero-limit-key-0001', fn () => 'qq:zero'); $this->fail('Must deny'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('quota', $e->errors()); }
        $vip = $this->policy('VIP', null, 'active', true);
        $this->membership($user, 'VIP');
        $this->assertSame($vip->id, $service->remaining($user)['policy_id']);
        $this->assertTrue($service->remaining($user)['unlimited']);
        $this->assertNull($service->remaining($user)['remaining']);
    }

    public function test_wib_midnight_starts_new_bucket_without_deleting_history(): void
    {
        $user = $this->person(); $this->policy('PEJUANG', 1);
        $service = app(QuickQuizQuotaService::class);
        $before = CarbonImmutable::parse('2026-11-10T16:59:59Z');
        $after = CarbonImmutable::parse('2026-11-10T17:00:00Z');
        $service->startWithQuota($user, 'before-midnight-01', fn () => 'qq:before', $before);
        $this->assertSame(0, $service->remaining($user, $before)['remaining']);
        $this->assertSame(1, $service->remaining($user, $after)['remaining']);
        $service->startWithQuota($user, 'after-midnight-001', fn () => 'qq:after', $after);
        $this->assertDatabaseCount('qq_quota_usages', 2);
        $this->assertDatabaseCount('qq_daily_quota_counters', 2);
    }

    public function test_idempotency_and_rollback_work_for_package_quotas(): void
    {
        $user = $this->person(); $this->policy('PEJUANG', 1);
        $service = app(QuickQuizQuotaService::class);
        $called = 0;
        $callback = function () use (&$called) { $called++; return 'qq:unique01'; };
        $first = $service->startWithQuota($user, 'same-package-key-01', $callback);
        $second = $service->startWithQuota($user, 'same-package-key-01', $callback);
        $this->assertFalse($first['resumed']);
        $this->assertTrue($second['resumed']);
        $this->assertSame(1, $called);
        $this->assertSame(0, $service->remaining($user)['remaining']);
        try { $service->startWithQuota($user, 'different-key-0001', fn () => 'qq:second'); $this->fail('Must exhaust'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('quota', $e->errors()); }
        $this->assertDatabaseCount('qq_quota_usages', 1);
    }

    public function test_failed_first_question_transaction_does_not_charge_user(): void
    {
        $user = $this->person(); $this->policy('PEJUANG', 3);
        try {
            app(QuickQuizQuotaService::class)->startWithQuota($user, 'rollback-package-001', function () {
                throw new \LogicException('Simulated attempt failure');
            });
            $this->fail('Expected rollback');
        } catch (\LogicException $e) { $this->assertSame('Simulated attempt failure', $e->getMessage()); }
        $this->assertDatabaseCount('qq_quota_usages', 0);
        $this->assertDatabaseCount('qq_daily_quota_counters', 0);
    }

    public function test_package_change_preserves_total_consumed_today(): void
    {
        $user = $this->person();
        $this->policy('PEJUANG', 1); $this->policy('ABDI_NEGARA', 3);
        $service = app(QuickQuizQuotaService::class);
        $service->startWithQuota($user, 'first-today-key-01', fn () => 'qq:first');
        $this->membership($user, 'ABDI_NEGARA');
        $this->assertSame(2, $service->remaining($user)['remaining']);
        $service->startWithQuota($user, 'second-today-key-1', fn () => 'qq:second');
        $this->assertSame(1, $service->remaining($user)['remaining']);
        $this->assertSame(2, QuickQuizDailyQuotaCounter::query()->firstOrFail()->consumed_count);
    }

    public function test_superadmin_can_configure_only_canonical_package_quotas(): void
    {
        $url = route('admin.qq-quota.index');
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($this->person('participant'))->get($url)->assertForbidden();
        $this->actingAs($this->person('operational_admin'))->get($url)->assertForbidden();
        $super = $this->person('superadmin');
        $this->actingAs($super)->get($url)->assertOk()
            ->assertSee('Pejuang')->assertSee('Abdi Negara')->assertSee('VIP')
            ->assertDontSee('Grant manfaat peserta');
        $this->put(route('admin.qq-quota.update', $this->plan('PEJUANG')),
            ['status' => 'active', 'confirmed' => 1])->assertSessionHasErrors('daily_limit');
        $this->put(route('admin.qq-quota.update', $this->plan('PEJUANG')),
            ['status' => 'active', 'daily_limit' => 2, 'confirmed' => 1])->assertRedirect();
        $this->assertDatabaseHas('qq_quota_policies', [
            'code' => 'PLAN_PEJUANG', 'scope' => 'plan', 'daily_limit' => 2, 'status' => 'active']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'qq.plan_quota_create', 'actor_id' => $super->id]);
        $this->post('/admin/quiz-kilat-kuota/grant', [])->assertNotFound();
    }

    public function test_superadmin_package_assignment_is_account_level_and_audited(): void
    {
        $user = $this->person();
        $admin = $this->person('superadmin');
        $this->policy('PEJUANG', 1); $this->policy('VIP', 8);
        $this->actingAs($admin)->post(route('admin.participants.plan', $user), [
            'member_plan_id' => $this->plan('VIP')->id,
            'reason' => 'Konfirmasi layanan paket telah disetujui secara administrasi.', 'confirmed' => 1,
        ])->assertRedirect();
        $this->assertSame('VIP', app(QuickQuizQuotaService::class)->remaining($user)['plan_code']);
        $this->assertDatabaseHas('access_grants', ['user_id' => $user->id,
            'grant_type' => 'member_plan', 'resource_type' => 'member_plan', 'status' => 'active']);
        $this->assertDatabaseMissing('access_grants', ['user_id' => $user->id, 'grant_type' => 'qq_quota']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'participant.member_plan_changed', 'subject_id' => $user->id]);
        $this->actingAs($this->person('operational_admin'))->post(route('admin.participants.plan', $user), [
            'member_plan_id' => $this->plan('PEJUANG')->id, 'confirmed' => 1,
            'reason' => 'Tidak sah untuk peran operasional admin.',
        ])->assertForbidden();
    }

    public function test_downgrade_to_pejuang_revokes_package_not_qq_or_cat(): void
    {
        $member = $this->person(); $admin = $this->person('superadmin');
        $this->membership($member, 'ABDI_NEGARA');
        $this->policy('PEJUANG', 2); $this->policy('ABDI_NEGARA', 5);
        $this->actingAs($admin)->post(route('admin.participants.plan', $member), [
            'member_plan_id' => $this->plan('PEJUANG')->id,
            'reason' => 'Paket berbayar telah berakhir berdasarkan keputusan akun.', 'confirmed' => 1,
        ])->assertRedirect();
        $this->assertSame('PEJUANG', app(QuickQuizQuotaService::class)->remaining($member)['plan_code']);
        $this->assertDatabaseHas('access_grants', ['user_id' => $member->id,
            'grant_type' => 'member_plan', 'status' => 'revoked']);
    }

    public function test_legacy_policy_and_usage_remain_readable_after_correction(): void
    {
        $user = $this->person();
        $legacy = QuickQuizQuotaPolicy::query()->create([
            'code' => 'FREE', 'name' => 'Legacy', 'scope' => 'base', 'daily_limit' => 5,
            'status' => 'active', 'unlimited' => false,
        ]);
        QuickQuizQuotaUsage::query()->create([
            'user_id' => $user->id, 'qq_quota_policy_id' => $legacy->id,
            'bucket_date_wib' => '2026-11-10', 'idempotency_key' => 'old-usage-key-01',
            'attempt_ref' => 'qq:legacy', 'limit_snapshot' => 5,
            'unlimited_snapshot' => false, 'created_at' => now(),
        ]);
        $this->policy('PEJUANG', 2);
        $this->assertSame(2, app(QuickQuizQuotaService::class)->remaining($user)['daily_limit']);
        $this->assertDatabaseCount('qq_quota_usages', 1);
    }

    public function test_participant_detail_displays_package_and_today_without_opening_qq_attempt_route(): void
    {
        $user = $this->person(); $this->policy('PEJUANG', 4);
        $this->actingAs($this->person('superadmin'))
            ->get(route('admin.participants.show', $user))->assertOk()
            ->assertSee('Paket peserta')->assertSee('Pejuang')->assertSee('4 tersisa');
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('member.quick-quizzes.start'));
    }
    public function test_inactive_account_and_admin_never_receive_quota_even_with_paid_membership(): void
    {
        $this->policy('PEJUANG', 2); $this->policy('VIP', 12);
        $suspended = $this->person('participant', 'suspended');
        $this->membership($suspended, 'VIP');
        $service = app(QuickQuizQuotaService::class);
        $this->assertSame('ACCOUNT_INACTIVE', $service->resolve($suspended)['reason']);
        $this->assertFalse($service->remaining($suspended)['allowed']);
        $admin = $this->person('superadmin');
        $this->assertSame('ACCOUNT_INACTIVE', $service->resolve($admin)['reason']);
        $this->assertNull($service->remaining($admin)['remaining']);
    }

}
