<?php

namespace Tests\Feature;

use App\Models\AccessGrant;
use App\Models\AuditLog;
use App\Models\ConsentRecord;
use App\Models\User;
use App\Services\MemberPlanService;
use App\Services\QuickQuizQuotaService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class SiapegawaiAdminInvitationTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role = 'superadmin', string $status = 'active'): User
    {
        return User::factory()->create(['role' => $role, 'status' => $status]);
    }

    private function invite(string $email = 'undangan@example.com'): User
    {
        Notification::fake();
        $this->actingAs($this->account())->post(route('admin.participants.store'), [
            'name' => 'Peserta Undangan', 'email' => $email,
            'role' => 'superadmin', 'status' => 'active', 'member_plan_id' => 9,
        ])->assertRedirect();
        return User::query()->where('email', $email)->sole();
    }

    private function legal(): void
    {
        config()->set('siapegawai.registration_enabled', false);
        config()->set('siapegawai.privacy_approved', true);
        config()->set('siapegawai.terms_approved', true);
        config()->set('siapegawai.privacy_version', '2026-11-approved');
    }

    public function test_superadmin_can_create_pending_participant_and_send_broker_invitation(): void
    {
        $root = $this->account();
        Notification::fake();
        $this->actingAs($root)->get(route('admin.participants.create'))->assertOk()->assertSee('Tambah peserta');
        $this->post(route('admin.participants.store'), [
            'name' => 'Peserta Baru', 'email' => ' PESERTA@EXAMPLE.COM ',
            'role' => 'superadmin', 'status' => 'active', 'member_plan_id' => 123,
        ])->assertRedirect();
        $member = User::query()->where('email', 'peserta@example.com')->sole();
        $this->assertSame('participant', $member->role);
        $this->assertSame('pending', $member->status);
        $this->assertNull($member->activated_at);
        $this->assertNotSame('PesertaBaru12345', $member->password);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $root->id, 'action' => 'participant.admin_created', 'subject_id' => $member->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'participant.invitation_sent', 'subject_id' => $member->id]);
        $this->assertDatabaseCount('registration_requests', 0);
        $this->assertDatabaseCount('consent_records', 0);
        $this->assertDatabaseCount('access_grants', 0);
        Notification::assertSentTo($member, ResetPassword::class);
        $this->actingAs($member)->get(route('dashboard'))->assertRedirect(route('registration.status'));
        $this->get(route('registration.status'))->assertOk()->assertSee('Menunggu onboarding');
    }

    public function test_only_superadmin_may_provision_or_resend_and_duplicate_email_is_rejected(): void
    {
        foreach (['operational_admin', 'participant'] as $role) {
            $actor = $this->account($role);
            $this->actingAs($actor)->get(route('admin.participants.create'))->assertForbidden();
            $this->post(route('admin.participants.store'), ['name'=>'Tidak Sah','email'=>uniqid().'@example.test'])->assertForbidden();
        }
        $this->actingAs($this->account('superadmin','suspended'))
            ->post(route('admin.participants.store'), ['name'=>'Tidak Sah','email'=>'suspended@example.test'])->assertForbidden();
        $this->assertDatabaseCount('audit_logs', 0);
        $root = $this->account();
        $existing = $this->account('operational_admin');
        $this->actingAs($root)->from(route('admin.participants.create'))
            ->post(route('admin.participants.store'), ['name'=>'Dup','email'=>$existing->email])
            ->assertSessionHasErrors('email');
        $this->assertSame('operational_admin', $existing->fresh()->role);
    }

    public function test_broker_password_proof_consent_and_free_activation_are_owner_only(): void
    {
        $member = $this->invite();
        // Isolate the password-ownership guard from the legal gate and local .env.
        $this->legal();
        $this->actingAs($member)->post(route('participant.invitation.accept'), ['consent'=>'1'])
            ->assertSessionHasErrors('consent');
        $this->assertSame('pending', $member->fresh()->status);
        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();
        $token = Password::createToken($member); // Test-only broker token, never shown in admin UI.
        $this->post(route('password.update'), [
            'token'=>$token, 'email'=>$member->email, 'password'=>'RahasiaUndangan1234',
            'password_confirmation'=>'RahasiaUndangan1234',
        ])->assertRedirect(route('login'));
        $this->assertDatabaseHas('audit_logs', ['action'=>'participant.invitation_password_set','subject_id'=>$member->id]);
        $this->post(route('login.store'), ['email'=>$member->email,'password'=>'RahasiaUndangan1234'])
            ->assertRedirect(route('registration.status'));
        $this->get(route('registration.status'))->assertOk()->assertSee('Sudah diatur');
        // UAT machines may have real local legal flags enabled after free registration.
        // Exercise the closed-legal case explicitly; never depend on .env defaults.
        config()->set('siapegawai.privacy_approved', false);
        config()->set('siapegawai.terms_approved', false);
        config()->set('siapegawai.privacy_version', 'DRAFT');
        $this->post(route('participant.invitation.accept'), ['consent'=>'1'])
            ->assertSessionHasErrors('consent');
        $this->assertSame('pending', $member->fresh()->status);
        $this->assertDatabaseMissing('consent_records', ['user_id' => $member->id]);
        $this->assertDatabaseMissing('access_grants', ['user_id' => $member->id]);
        $this->legal();
        $this->post(route('participant.invitation.accept'), [])->assertSessionHasErrors('consent');
        $this->post(route('participant.invitation.accept'), ['consent'=>'1'])
            ->assertRedirect(route('dashboard'));
        $this->assertSame('active', $member->fresh()->status);
        $this->assertNull($member->fresh()->activated_by);
        $this->assertDatabaseHas('consent_records', [
            'user_id'=>$member->id, 'source'=>'admin_invitation', 'policy_version'=>'2026-11-approved',
        ]);
        $this->assertDatabaseHas('access_grants', [
            'user_id'=>$member->id, 'grant_type'=>'member', 'status'=>'active', 'granted_by'=>null,
        ]);
        $this->assertDatabaseMissing('access_grants', ['user_id'=>$member->id, 'grant_type'=>'member_plan']);
        $this->assertDatabaseMissing('access_grants', ['user_id'=>$member->id, 'grant_type'=>'qq_quota']);
        $this->assertSame('PEJUANG', app(MemberPlanService::class)->current($member->fresh())['plan']->code);
        $this->assertSame('POLICY_NOT_CONFIGURED', app(QuickQuizQuotaService::class)->remaining($member->fresh())['reason']);
        $this->post(route('participant.invitation.accept'), ['consent'=>'1'])->assertRedirect(route('dashboard'));
        $this->assertSame(1, AccessGrant::query()->where('user_id',$member->id)->count());
        $this->assertSame(1, ConsentRecord::query()->where('user_id',$member->id)->count());
        $this->assertSame(1, AuditLog::query()->where('subject_id',$member->id)
            ->where('action','participant.invitation_accepted')->count());
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_historical_pending_registration_is_not_treated_as_an_admin_invitation(): void
    {
        // Isolate invitation authorization from the independently closed legal gate.
        $this->legal();
        $pending = $this->account('participant', 'pending');
        $this->actingAs($pending)->post(route('participant.invitation.accept'), ['consent'=>'1'])->assertForbidden();
        $this->assertSame('pending', $pending->fresh()->status);
        $this->assertDatabaseCount('consent_records', 0);
    }

    public function test_resend_is_superadmin_only_and_does_not_change_participant_access(): void
    {
        $member = $this->invite();
        Notification::fake();
        $this->actingAs($this->account('operational_admin'))
            ->post(route('admin.participants.invitation.resend', $member))->assertForbidden();
        $this->actingAs($this->account())->post(route('admin.participants.invitation.resend', $member))
            ->assertRedirect();
        $this->assertSame('pending', $member->fresh()->status);
        $this->assertDatabaseCount('access_grants', 0);
    }
}
