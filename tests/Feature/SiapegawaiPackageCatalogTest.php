<?php

namespace Tests\Feature;

use App\Models\AccessGrant;
use App\Models\MemberPlan;
use App\Models\MemberPlanVersion;
use App\Models\PackageUpgradeRequest;
use App\Models\QuickQuizQuotaPolicy;
use App\Models\User;
use App\Services\MemberPlanService;
use App\Services\PackageCatalogService;
use App\Services\QuickQuizQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiapegawaiPackageCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Isolate business validation from shared user-key request buckets; rate limits
        // remain enforced in production routes and are tested separately.
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        // Feature assertions exercise rendered content and business rules; asset
        // compilation is verified separately by npm run build. Do not let a stale
        // public/build/manifest.json convert valid HTTP responses into 500 errors.
        $this->withoutVite();
    }

    private function admin(string $role = 'superadmin'): User { return User::factory()->create(['role'=>$role, 'status'=>'active']); }
    private function participant(): User { return User::factory()->create(['role'=>'participant','status'=>'active']); }
    private function plan(string $code): MemberPlan { return MemberPlan::query()->where('code',$code)->firstOrFail(); }
    private function makePlan(User $actor, string $code = 'PROFESIONAL'): MemberPlan
    {
        return app(PackageCatalogService::class)->create($actor, [
            'code'=>$code, 'name'=>'Paket Profesional', 'tier'=>5, 'is_paid'=>true,
            'summary'=>'Informasi program mandiri yang akan disiapkan.', 'benefits'=>'Catatan belajar mandiri',
        ]);
    }

    public function test_legacy_plan_codes_and_public_copy_are_preserved_without_opening_paid_sales(): void
    {
        $this->assertSame(['PEJUANG','ABDI_NEGARA','VIP'], MemberPlan::query()->orderBy('tier')->pluck('code')->all());
        $this->assertDatabaseCount('member_plan_versions', 3);
        $this->assertSame('requestable',$this->plan('PEJUANG')->availability);
        $this->assertSame('planned',$this->plan('ABDI_NEGARA')->availability);
        $this->assertSame('planned',$this->plan('VIP')->availability);
        $this->get('/paket')->assertOk()->assertSee('Pejuang · gratis')->assertSee('VIP Mentoring')
            ->assertSee('Rencana pendampingan tutor')->assertDontSee('Ajukan perubahan paket');
        $this->assertDatabaseCount('access_grants', 0);
        $this->assertDatabaseCount('qq_quota_policies', 0);
    }

    public function test_only_active_superadmin_manages_catalog_and_no_guest_mutations(): void
    {
        $this->get('/admin/paket')->assertRedirect(route('login'));
        $this->actingAs($this->admin('operational_admin'))->get('/admin/paket')->assertForbidden();
        $this->actingAs($this->participant())->post('/admin/paket',['code'=>'FOURTH'])->assertForbidden();
        $this->actingAs($this->admin())->get('/admin/paket')->assertOk()
            ->assertSee('Daftar paket')->assertSee('pkg-create-dialog');
    }

    public function test_fourth_and_fifth_plans_are_dynamic_and_unconfigured_quota_fails_closed(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.packages.store'), [
            'code'=>'PROFESIONAL','name'=>'Profesional', 'tier'=>4, 'is_paid'=>1,
            'summary'=>'Informasi kelas persiapan mandiri tanpa hak tambahan.', 'benefits'=>'Panduan belajar umum',
        ])->assertSessionHasErrors('benefits'); // CAT/Kelas claims need explicit runtime support
        $this->actingAs($admin)->post(route('admin.packages.store'), [
            'code'=>'PROFESIONAL','name'=>'Profesional','tier'=>4,'is_paid'=>1,
            'summary'=>'Informasi paket baru.', 'benefits'=>'Catatan personal',
        ])->assertRedirect();
        $new = $this->plan('PROFESIONAL');
        $this->assertNull($new->published_version_id);
        $this->assertSame('planned',$new->availability);
        $this->assertDatabaseCount('member_plans', 4);
        $user = $this->participant();
        $this->assertSame('POLICY_NOT_CONFIGURED',app(QuickQuizQuotaService::class)->remaining($user)['reason']);
        $catalog = app(PackageCatalogService::class);
        $draft = $new->versions()->where('status', 'draft')->firstOrFail();
        $catalog->publish($admin, $new, $catalog->fingerprint($draft));
        $new->refresh();
        app(MemberPlanService::class)->setPlan($admin,$user,$new,'Paket diputuskan secara manual oleh admin.');
        $this->assertSame('PROFESIONAL',app(MemberPlanService::class)->current($user)['plan']->code);
        $this->assertFalse(app(QuickQuizQuotaService::class)->resolve($user)['allowed']);
        $this->makePlan($admin,'PAKET_KELIMA');
        $this->assertDatabaseCount('member_plans',5);
    }

    public function test_versioned_draft_is_immutable_to_public_until_publish_and_discard_works(): void
    {
        $admin = $this->admin(); $service = app(PackageCatalogService::class);
        $plan = $this->plan('PEJUANG');
        $service->startRevision($admin,$plan);
        $draft = $plan->versions()->where('status','draft')->firstOrFail();
        $old = $plan->published_version_id;
        $this->actingAs($admin)->post(route('admin.packages.preview',$plan), [
            'fingerprint'=>$service->fingerprint($draft),'title'=>'Nama baru','summary'=>'Teks pratinjau baru', 'benefits'=>"Rangkuman materi",
        ])->assertOk()->assertSee('Teks pratinjau baru');
        $this->assertSame($old,$plan->fresh()->published_version_id);
        $service->updateDraft($admin,$plan, ['fingerprint'=>$service->fingerprint($draft),
            'title'=>'Pejuang Terbaru','summary'=>'Teks setelah terbit.', 'benefits'=>'Materi ringkas']);
        $this->get('/paket')->assertSee('Pejuang · gratis')->assertDontSee('Pejuang Terbaru');
        $draft->refresh();
        $this->actingAs($admin)->post(route('admin.packages.publish',$plan), [
            'fingerprint'=>($service->fingerprint($draft)[0] === '0' ? '1' : '0').substr($service->fingerprint($draft),1),'confirmed'=>1,
        ])->assertSessionHasErrors('fingerprint');
        $this->actingAs($admin)->post(route('admin.packages.publish',$plan), [
            'fingerprint'=>$service->fingerprint($draft),'confirmed'=>1,
        ])->assertRedirect();
        $this->get('/paket')->assertSee('Pejuang Terbaru')->assertDontSee('Pejuang · gratis');
        $this->assertSame('superseded', MemberPlanVersion::findOrFail($old)->status);
        $service->startRevision($admin,$plan);
        $service->discard($admin,$plan);
        $this->assertSame($draft->id,$plan->fresh()->published_version_id);
        $this->get('/paket')->assertSee('Pejuang Terbaru');
    }

    public function test_published_requestable_plan_can_receive_idempotent_request_without_grants(): void
    {
        $admin = $this->admin(); $plan = $this->makePlan($admin);
        $catalog = app(PackageCatalogService::class);
        $draft = $plan->versions()->where('status','draft')->firstOrFail();
        $catalog->publish($admin,$plan,$catalog->fingerprint($draft));
        $catalog->availability($admin,$plan,'requestable');
        $member = $this->participant();
        $this->post(route('member.packages.request.store',$plan), ['confirmed'=>1])->assertRedirect(route('login'));
        $this->actingAs($member)->post(route('member.packages.request.store',$plan), ['confirmed'=>1])->assertRedirect();
        $this->actingAs($member)->post(route('member.packages.request.store',$plan), ['confirmed'=>1])->assertRedirect();
        $this->assertDatabaseCount('package_upgrade_requests',1);
        $this->assertDatabaseCount('access_grants',0);
        $upgrade = PackageUpgradeRequest::firstOrFail();
        $this->actingAs($member)->get(route('member.packages.request',$upgrade))->assertOk()->assertSee($upgrade->reference);
        $this->actingAs($this->participant())->get(route('member.packages.request',$upgrade))->assertNotFound();
        config()->set('siapegawai.whatsapp_number',null);
        $this->actingAs($member)->get(route('member.packages.request',$upgrade))->assertSee('belum terkonfigurasi');
    }

    public function test_manual_admin_decision_requires_verification_and_is_not_replayable(): void
    {
        $admin=$this->admin(); $plan=$this->makePlan($admin);
        $catalog=app(PackageCatalogService::class);
        $draft=$plan->versions()->where('status','draft')->firstOrFail();
        $catalog->publish($admin,$plan,$catalog->fingerprint($draft));
        $catalog->availability($admin,$plan,'requestable');
        $user=$this->participant();
        $this->actingAs($user)->post(route('member.packages.request.store',$plan), ['confirmed'=>1])->assertRedirect();
        $upgrade=PackageUpgradeRequest::firstOrFail();
        $url=route('admin.packages.requests.decide',$upgrade);
        $this->actingAs($this->admin('operational_admin'))->post($url,[
            'decision'=>'completed','reason'=>'Pembayaran dicek manual.', 'confirmed'=>1,'manual_verified'=>1,
        ])->assertForbidden();
        $this->actingAs($admin)->post($url,[
            'decision'=>'completed','reason'=>'Pembayaran diverifikasi manual melalui admin.', 'confirmed'=>1,
        ])->assertSessionHasErrors('manual_verified');
        $this->assertSame('PEJUANG',app(MemberPlanService::class)->current($user)['plan']->code);
        $this->actingAs($admin)->post($url,[
            'decision'=>'completed','reason'=>'Pembayaran diverifikasi manual bersama petugas.',
            'confirmed'=>1,'manual_verified'=>1,
        ])->assertRedirect();
        $this->assertSame('PROFESIONAL',app(MemberPlanService::class)->current($user)['plan']->code);
        $this->assertSame('completed',$upgrade->fresh()->status);
        $this->assertDatabaseHas('audit_logs',['action'=>'package.request_completed','subject_id'=>$upgrade->id]);
        $this->actingAs($admin)->post($url,[
            'decision'=>'completed','reason'=>'Ulangan terlarang setelah proses sudah selesai.',
            'confirmed'=>1,'manual_verified'=>1,
        ])->assertSessionHasErrors('request');
        $this->assertSame(1,AccessGrant::query()->where('user_id',$user->id)->where('grant_type','member_plan')->where('status','active')->count());
    }

    public function test_archiving_plan_does_not_revoke_existing_membership_and_stops_new_requests(): void
    {
        $admin=$this->admin(); $plan=$this->makePlan($admin);
        $catalog=app(PackageCatalogService::class);
        $draft=$plan->versions()->where('status','draft')->firstOrFail();
        $catalog->publish($admin,$plan,$catalog->fingerprint($draft));
        $catalog->availability($admin,$plan,'requestable');
        $member=$this->participant();
        app(MemberPlanService::class)->setPlan($admin,$member,$plan,'Paket sudah disetujui secara manual oleh admin.');
        $catalog->availability($admin,$plan,'archived');
        $this->get('/paket')->assertDontSee('Paket Profesional');
        $this->assertSame('PROFESIONAL',app(MemberPlanService::class)->current($member)['plan']->code);
        $this->actingAs($this->participant())->post(route('member.packages.request.store',$plan),['confirmed'=>1])->assertSessionHasErrors('package');
    }

    public function test_ordering_is_separate_from_creation_and_new_packages_append_to_last_position(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.packages.index'))->assertOk()
            ->assertSee('Atur urutan')->assertDontSee('name="tier"', false);
        $this->actingAs($admin)->get(route('admin.packages.ordering'))->assertOk()
            ->assertSee('pkg-sort-list')->assertSee('Naik')->assertSee('Turun');
        $this->actingAs($admin)->post(route('admin.packages.store'), [
            'code' => 'PAKET_BARU', 'name' => 'Paket Baru', 'is_paid' => 1,
            'summary' => 'Pilihan belajar mandiri.', 'benefits' => 'Tinjauan singkat',
        ])->assertRedirect();
        $this->assertSame(4, $this->plan('PAKET_BARU')->tier);
        $this->assertSame(['PEJUANG','ABDI_NEGARA','VIP','PAKET_BARU'],
            MemberPlan::query()->orderBy('tier')->pluck('code')->all());
        $this->assertDatabaseCount('member_plans', 4);
    }

    public function test_separate_sorting_changes_public_order_and_audits_without_changing_memberships(): void
    {
        $admin = $this->admin(); $catalog = app(PackageCatalogService::class);
        $plans = MemberPlan::query()->orderBy('tier')->get()->keyBy('code');
        $fingerprint = $catalog->orderFingerprint();
        $newOrder = [$plans['VIP']->id, $plans['PEJUANG']->id, $plans['ABDI_NEGARA']->id];
        $this->actingAs($admin)->post(route('admin.packages.ordering.save'), [
            'fingerprint' => $fingerprint, 'order' => $newOrder, 'confirmed' => '1',
        ])->assertRedirect(route('admin.packages.ordering'));
        $this->assertSame(['VIP','PEJUANG','ABDI_NEGARA'],
            MemberPlan::query()->orderBy('tier')->pluck('code')->all());
        $this->get('/paket')->assertOk()->assertSeeInOrder(['VIP Mentoring', 'Pejuang · gratis', 'Abdi Negara']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'package.reorder']);
        $this->assertDatabaseCount('access_grants', 0);
        $this->assertDatabaseCount('qq_quota_usages', 0);
        $this->assertDatabaseCount('package_upgrade_requests', 0);
        $this->assertDatabaseCount('member_plan_versions', 3);
        $this->assertDatabaseCount('member_plans', 3);
    }

    public function test_sorting_rejects_stale_missing_duplicate_foreign_and_non_superadmin_requests(): void
    {
        $admin = $this->admin(); $catalog = app(PackageCatalogService::class);
        $ids = MemberPlan::query()->orderBy('tier')->pluck('id')->all();
        $first = $catalog->orderFingerprint();
        $url = route('admin.packages.ordering.save');
        $this->get(route('admin.packages.ordering'))->assertRedirect(route('login'));
        $this->actingAs($this->admin('operational_admin'))->get(route('admin.packages.ordering'))->assertForbidden();
        $this->actingAs($this->admin('operational_admin'))->post($url, [
            'fingerprint' => $first, 'order' => array_reverse($ids), 'confirmed' => '1',
        ])->assertForbidden();
        $this->actingAs($admin)->post($url, [
            'fingerprint' => $first, 'order' => [$ids[0], $ids[0], $ids[2]], 'confirmed' => '1',
        ])->assertSessionHasErrors('order.1');
        $this->actingAs($admin)->post($url, [
            'fingerprint' => $first, 'order' => [$ids[0], 999999, $ids[2]], 'confirmed' => '1',
        ])->assertSessionHasErrors('order');
        $this->actingAs($admin)->post($url, [
            'fingerprint' => $first, 'order' => [$ids[0], $ids[1]], 'confirmed' => '1',
        ])->assertSessionHasErrors('order');
        $this->actingAs($admin)->post($url, [
            'fingerprint' => $first, 'order' => array_reverse($ids),
        ])->assertSessionHasErrors('confirmed');
        $this->assertSame(0, \App\Models\AuditLog::query()->where('action', 'package.reorder')->count());
        $this->actingAs($admin)->post($url, [
            'fingerprint' => $first, 'order' => $ids, 'confirmed' => '1',
        ])->assertRedirect();
        $this->assertSame(0, \App\Models\AuditLog::query()->where('action', 'package.reorder')->count());
        $this->actingAs($admin)->post($url, [
            'fingerprint' => $first, 'order' => array_reverse($ids), 'confirmed' => '1',
        ])->assertRedirect();
        $this->actingAs($admin)->post($url, [
            'fingerprint' => $first, 'order' => $ids, 'confirmed' => '1',
        ])->assertSessionHasErrors('order');
        $this->assertSame(1, \App\Models\AuditLog::query()->where('action', 'package.reorder')->count());
    }

    public function test_manage_package_uses_full_page_and_shows_published_snapshot_and_draft_separately(): void
    {
        $admin = $this->admin();
        $plan = $this->plan('PEJUANG');
        $published = $plan->publishedVersion;
        $this->actingAs($admin)->get(route('admin.packages.index'))->assertOk()
            ->assertSee('pkg-create-dialog')->assertDontSee('pkg-edit-dialog')
            ->assertSee('href="'.route('admin.packages.show', $plan).'"', false);

        $this->actingAs($admin)->get(route('admin.packages.show', $plan))->assertOk()
            ->assertSee('Versi terbit')->assertSee('Manfaat pada versi terbit')
            ->assertSee('Ketersediaan paket')->assertSee('Riwayat versi')
            ->assertSee($published->title)->assertSee($published->summary)
            ->assertSee('Buat draft revisi')->assertDontSee('pkg-edit-dialog');

        $catalog = app(PackageCatalogService::class);
        $catalog->startRevision($admin, $plan);
        $draft = $plan->versions()->where('status', 'draft')->firstOrFail();
        $catalog->updateDraft($admin, $plan, [
            'fingerprint' => $catalog->fingerprint($draft),
            'title' => 'Rancangan Pejuang v2',
            'summary' => 'Ringkasan baru yang belum diterbitkan.',
            'benefits' => "Rangkuman adaptif\nPanduan mandiri",
        ]);
        $this->assertSame($published->id, $plan->fresh()->published_version_id);
        $this->actingAs($admin)->get(route('admin.packages.show', $plan))->assertOk()
            ->assertSee('Versi terbit')->assertSee($published->summary)
            ->assertSee('Rancangan Pejuang v2')->assertSee('Ringkasan baru yang belum diterbitkan.')
            ->assertSee('Rangkuman adaptif')->assertSee('Panduan mandiri')
            ->assertSee('Pratinjau perubahan')->assertSee('Simpan draft')
            ->assertSee('Terbitkan versi ini')->assertSee('Batalkan draft')
            ->assertSee('data-pkg-detail-draft', false)
            ->assertSee('data-pkg-detail-publish', false);
        $this->get('/paket')->assertOk()->assertSee($published->title)
            ->assertDontSee('Rancangan Pejuang v2');

        // Full-page details remain protected by the existing superadmin gate.
        $this->actingAs($this->admin('operational_admin'))
            ->get(route('admin.packages.show', $plan))->assertForbidden();
        $this->actingAs($this->participant())
            ->get(route('admin.packages.show', $plan))->assertForbidden();
    }

    public function test_explicit_json_package_details_still_preserve_existing_client_contract(): void
    {
        $admin = $this->admin();
        $plan = $this->plan('PEJUANG');
        $published = $plan->publishedVersion;

        // withHeader() persists in this test client's default headers. Keep the
        // Accept: application/json contract in a separate test so the HTML
        // acceptance test always represents a normal browser navigation.
        $this->actingAs($admin)->withHeader('Accept', 'application/json')
            ->get(route('admin.packages.show', $plan))->assertOk()
            ->assertJsonPath('code', 'PEJUANG')
            ->assertJsonPath('published.title', $published->title)
            ->assertJsonPath('published.summary', $published->summary);
    }

    public function test_new_plan_details_show_editable_first_draft_without_publishing_it(): void
    {
        $admin = $this->admin();
        $plan = $this->makePlan($admin, 'PRODUK_BARU');
        $this->actingAs($admin)->get(route('admin.packages.show', $plan))->assertOk()
            ->assertSee('Belum terbit')->assertSee('Draft v1')
            ->assertSee('pkg-detail-preview-open')->assertSee('Terbitkan versi ini');
        $this->assertNull($plan->fresh()->published_version_id);
        $this->assertDatabaseCount('access_grants', 0);
    }

    public function test_qq_quota_can_be_configured_for_new_plan_and_does_not_change_history(): void
    {
        $admin=$this->admin(); $plan=$this->makePlan($admin);
        // The assignment guard is intentional: only a published plan may become
        // active. A quota fixture must publish before calling setPlan().
        $catalog=app(PackageCatalogService::class);
        $draft=$plan->versions()->where('status','draft')->firstOrFail();
        $catalog->publish($admin,$plan,$catalog->fingerprint($draft));
        $member=$this->participant();
        app(MemberPlanService::class)->setPlan($admin,$member,$plan,'Paket manual untuk tes kuota dinamis.');
        $this->assertFalse(app(QuickQuizQuotaService::class)->resolve($member)['allowed']);
        QuickQuizQuotaPolicy::query()->create(['member_plan_id'=>$plan->id,'code'=>'PLAN_PROFESIONAL',
            'name'=>'Kuota Paket Profesional','scope'=>'plan','daily_limit'=>3,'unlimited'=>false,'status'=>'active']);
        $this->assertSame(3,app(QuickQuizQuotaService::class)->remaining($member)['remaining']);
        $this->assertDatabaseCount('qq_quota_usages',0);
    }
}
