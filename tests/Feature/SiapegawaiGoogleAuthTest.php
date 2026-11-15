<?php

namespace Tests\Feature;

use App\Models\AccessGrant;
use App\Models\ConsentRecord;
use App\Models\User;
use App\Models\UserIdentity;
use App\Services\GoogleIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class SiapegawaiGoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    private function ready(bool $registration = true): void
    {
        config()->set('siapegawai.google_auth_enabled', true);
        config()->set('services.google', [
            'client_id' => 'local-fake-client', 'client_secret' => 'local-fake-secret',
            'redirect' => 'http://localhost:8000/auth/google/callback',
        ]);
        config()->set('siapegawai.registration_enabled', $registration);
        config()->set('siapegawai.privacy_approved', true);
        config()->set('siapegawai.terms_approved', true);
        config()->set('siapegawai.privacy_version', '2026-11-test-approved');
    }

    private function provider(string $sub = '123456789012345678901', string $email = 'google@example.test', bool $verified = true): void
    {
        $google = (new GoogleUser())->setRaw([
            'sub' => $sub, 'email' => $email,
            'email_verified' => $verified, 'name' => 'Google Peserta',
        ])->map(['id' => $sub, 'name' => 'Google Peserta', 'email' => $email]);
        $mock = Mockery::mock();
        $mock->shouldReceive('user')->once()->andReturn($google);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($mock);
    }

    private function flow(string $purpose = 'signin', ?int $actor = null): array
    {
        return ['siapegawai_google_flow' => [
            'purpose' => $purpose, 'actor' => $actor, 'created' => now()->timestamp,
        ]];
    }

    public function test_disabled_provider_fails_closed_and_password_login_stays_available(): void
    {
        config()->set('siapegawai.google_auth_enabled', false);
        $this->get(route('google.redirect'))->assertStatus(503);
        $this->get(route('google.callback'))->assertStatus(503);
        $this->get(route('login'))->assertOk()->assertDontSee('Lanjut dengan Google');
        $this->assertDatabaseCount('user_identities', 0);
    }

    public function test_google_cta_uses_full_width_account_button_with_local_colored_mark(): void
    {
        $this->ready();
        $this->get(route('login'))->assertOk()
            ->assertSee('account-google-actions')->assertSee('account-auth-divider')
            ->assertSee('account-google-button')->assertSee('account-google-icon')
            ->assertSee('fill="#4285F4"', false)->assertSee('Masuk dengan Google')
            ->assertSee('href="'.route('google.redirect').'"', false);
        $this->get(route('register'))->assertOk()
            ->assertSee('account-google-actions')->assertSee('account-auth-divider')
            ->assertSee('account-google-button')->assertSee('account-google-icon')
            ->assertSee('Daftar dengan Google')
            ->assertSee('Akun Google baru tetap harus melengkapi WhatsApp');

        $css = file_get_contents(resource_path('css/application.css'));
        $this->assertStringContainsString('.shell-account .account-google-button { width: 100%;', $css);
        $this->assertStringContainsString('.shell-account .account-google-icon { width: 20px;', $css);

        config()->set('siapegawai.google_auth_enabled', false);
        $this->get(route('login'))->assertOk()->assertDontSee('account-google-button');
        $this->get(route('register'))->assertOk()->assertDontSee('account-google-button');
    }

    public function test_missing_onboarding_session_explains_expiry_without_creating_account(): void
    {
        $this->ready();
        $this->get(route('google.onboarding'))->assertRedirect(route('login'))
            ->assertSessionHas('google_feedback.code', 'expired');
        $this->post(route('google.onboarding.store'), [
            'nonce' => str_repeat('a',32), 'name' => 'Peserta Uji',
            'whatsapp_number' => '081234567891', 'consent' => '1',
        ])->assertRedirect(route('login'))->assertSessionHas('google_feedback.code','expired');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_existing_email_explains_collision_and_safe_account_linking_without_js(): void
    {
        $this->ready();
        $member = User::factory()->create(['email'=>'existing@example.test', 'role'=>'participant', 'status'=>'active']);
        $this->provider('new-safe-subject','existing@example.test');
        $this->withSession($this->flow())->get(route('google.callback'))
            ->assertRedirect(route('login'))->assertSessionHas('google_feedback.code','email_exists');
        $this->get(route('login'))->assertOk()->assertSee('Email sudah terdaftar')
            ->assertSee('HASIL LOGIN GOOGLE')->assertSee('Masuk dengan kata sandi')
            ->assertSee('Lupa kata sandi?')->assertSee('Profil saya')
            ->assertSee('account-google-feedback');
        $this->assertGuest();
        $this->assertDatabaseMissing('user_identities',['user_id'=>$member->id]);
    }

    public function test_cancel_expired_network_and_unverified_google_have_visible_explanations(): void
    {
        $this->ready();
        $this->withSession($this->flow())->get(route('google.callback').'?error=access_denied')
            ->assertRedirect(route('login'))->assertSessionHas('google_feedback.code','cancelled');
        $this->get(route('login'))->assertSee('Login Google dibatalkan');
        $this->withSession($this->flow())->get(route('google.callback').'?error=server_error')
            ->assertRedirect(route('login'))->assertSessionHas('google_feedback.code','provider_error');
        $this->get(route('login'))->assertSee('Koneksi Google gagal');
        $this->get(route('google.callback'))->assertRedirect(route('login'))
            ->assertSessionHas('google_feedback.code','expired');
        $this->get(route('login'))->assertSee('Sesi Google telah berakhir');
        $this->provider(verified:false);
        $this->withSession($this->flow())->get(route('google.callback'))
            ->assertRedirect(route('login'))->assertSessionHas('google_feedback.code','invalid_identity');
        $this->get(route('login'))->assertSee('Identitas Google belum terverifikasi');
        $this->assertDatabaseCount('users',0);
    }

    public function test_link_cancel_stays_in_authenticated_profile_and_warning_is_visible(): void
    {
        $this->ready();
        $member = User::factory()->create(['role'=>'participant','status'=>'active']);
        $this->actingAs($member)->withSession($this->flow('link',$member->id))
            ->get(route('google.callback').'?error=access_denied')
            ->assertRedirect(route('member.profile.edit'))->assertSessionHas('google_link_feedback');
        $this->get(route('member.profile.edit'))->assertOk()->assertSee('Penautan Google dibatalkan');
        $this->assertDatabaseCount('user_identities',0);
    }

    public function test_blocked_and_closed_google_attempts_offer_explanations_without_linking(): void
    {
        $this->ready();
        $locked = User::factory()->create(['role'=>'participant','status'=>'suspended']);
        UserIdentity::query()->create(['user_id'=>$locked->id,'provider'=>'google','provider_subject'=>'locked-google']);
        $this->provider('locked-google',$locked->email);
        $this->withSession($this->flow())->get(route('google.callback'))
            ->assertRedirect(route('login'))->assertSessionHas('google_feedback.code','blocked');
        $this->get(route('login'))->assertSee('Akun belum dapat masuk')->assertSee('Hubungi pengelola');
        config()->set('siapegawai.registration_enabled',false);
        $this->provider('new-google','new@example.test');
        $this->withSession($this->flow())->get(route('google.callback'))
            ->assertRedirect(route('login'))->assertSessionHas('google_feedback.code','registration_closed');
        $this->get(route('login'))->assertSee('Pendaftaran Google belum tersedia')->assertDontSee('Coba Google lagi');
    }

    public function test_existing_admin_or_pending_email_does_not_offer_unsafe_linking(): void
    {
        $this->ready();
        foreach (['superadmin', 'participant'] as $role) {
            $account = User::factory()->create(['role'=>$role,'status'=>$role==='participant' ? 'pending' : 'active']);
            $this->provider('other_sub_'.$account->id, $account->email);
            $this->withSession($this->flow())->get(route('google.callback'))
                ->assertRedirect(route('login'))->assertSessionHas('google_feedback.code','account_not_linkable');
            $this->get(route('login'))->assertSee('Akun tidak dapat ditautkan otomatis')
                ->assertSee('Hubungi pengelola')->assertDontSee('Profil saya');
            $this->assertGuest();
            $this->assertDatabaseMissing('user_identities',['user_id'=>$account->id]);
        }
    }

    public function test_google_flash_supports_warnings_and_errors_and_escapes_content(): void
    {
        $this->withSession(['warning'=>'Peringatan Google','error'=>'Kesalahan Google'])
            ->get(route('login'))->assertOk()->assertSee('data-toast-type="warning"',false)
            ->assertSee('data-toast-type="error"',false)->assertSee('Peringatan Google')
            ->assertSee('Kesalahan Google');
        $this->withSession(['warning'=>'<script>alert(1)</script>'])
            ->get(route('login'))->assertDontSee('<script>alert(1)</script>',false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_callback_without_flow_or_with_expired_flow_never_creates_account(): void
    {
        $this->ready();
        $this->get(route('google.callback'))->assertRedirect(route('login'));
        $this->withSession(['siapegawai_google_flow' => ['purpose'=>'signin', 'created'=>now()->subMinutes(30)->timestamp]])
            ->get(route('google.callback'))->assertRedirect(route('login'));
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('user_identities', 0);
    }

    public function test_new_google_identity_requires_owner_consent_and_activates_only_pejuang(): void
    {
        $this->ready();
        $this->provider();
        $this->withSession($this->flow())->get(route('google.callback'))->assertRedirect(route('google.onboarding'));
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->get(route('google.onboarding'))->assertOk()->assertSee('Lengkapi akun Google');
        $pending = session('siapegawai_google_pending');
        $this->post(route('google.onboarding.store'), [
            'nonce' => $pending['nonce'], 'name' => 'Peserta Baru',
            'whatsapp_number' => '081234567891',
        ])->assertSessionHasErrors('consent');
        $this->assertDatabaseCount('users', 0);
        $response = $this->post(route('google.onboarding.store'), [
            'nonce' => $pending['nonce'], 'name' => 'Peserta Baru',
            'whatsapp_number' => '081234567891', 'consent' => '1',
            'status' => 'superadmin', 'member_plan_id' => 100,
        ]);
        $response->assertStatus(303)->assertRedirect(route('dashboard'))
            ->assertSessionHas('success', 'Akun Google berhasil dibuat. Selamat datang! Paket dasar Pejuang aktif.');
        $user = User::query()->where('email', 'google@example.test')->sole();
        $this->assertSame('participant', $user->role);
        $this->assertSame('active', $user->status);
        $this->assertNull($user->activated_by);
        $this->assertSame('6281234567891', $user->whatsapp_number);
        $this->assertDatabaseHas('user_identities', ['user_id'=>$user->id, 'provider'=>'google', 'provider_subject'=>'123456789012345678901']);
        $this->assertDatabaseHas('consent_records', ['user_id'=>$user->id, 'source'=>'google_registration', 'policy_version'=>'2026-11-test-approved']);
        $this->assertDatabaseHas('access_grants', ['user_id'=>$user->id, 'grant_type'=>'member', 'status'=>'active']);
        $this->assertDatabaseMissing('access_grants', ['user_id'=>$user->id, 'grant_type'=>'member_plan']);
        $this->assertDatabaseMissing('access_grants', ['user_id'=>$user->id, 'grant_type'=>'qq_quota']);
        $this->assertDatabaseCount('registration_requests', 0);
        // The successful signup cleared one-time state. After logout, replay cannot create an account.
        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->post(route('google.onboarding.store'), [
            'nonce' => $pending['nonce'], 'name' => 'Peserta Baru', 'whatsapp_number'=>'081234567891', 'consent'=>'1',
        ])->assertRedirect(route('login'))->assertSessionHas('google_feedback.code','expired');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_closed_registration_blocks_new_google_but_linked_active_user_can_login(): void
    {
        $this->ready(false);
        $this->provider();
        $this->withSession($this->flow())->get(route('google.callback'))->assertRedirect(route('login'));
        $this->assertDatabaseCount('users', 0);
        $member = User::factory()->create(['role'=>'participant', 'status'=>'active']);
        UserIdentity::query()->create(['user_id'=>$member->id, 'provider'=>'google','provider_subject'=>'123456789012345678901']);
        $this->provider();
        $this->withSession($this->flow())->get(route('google.callback'))->assertStatus(303)
            ->assertRedirect(route('dashboard'))->assertSessionHas('success', 'Berhasil masuk dengan Google.');
        $this->assertAuthenticatedAs($member);
    }

    public function test_email_collision_does_not_auto_link_or_login_existing_member_or_admin(): void
    {
        $this->ready();
        foreach (['participant', 'superadmin'] as $role) {
            $account = User::factory()->create(['role'=>$role,'status'=>'active']);
            $this->provider('subject_'.$account->id, $account->email);
            $this->withSession($this->flow())->get(route('google.callback'))->assertRedirect(route('login'));
            $this->assertGuest();
            $this->assertDatabaseMissing('user_identities', ['user_id'=>$account->id]);
        }
    }

    public function test_linked_inactive_or_admin_accounts_cannot_login_with_google(): void
    {
        $this->ready();
        foreach (['pending','suspended','rejected'] as $status) {
            $member = User::factory()->create(['role'=>'participant','status'=>$status]);
            $subject = 'sub_'.$member->id;
            UserIdentity::query()->create(['user_id'=>$member->id, 'provider'=>'google', 'provider_subject'=>$subject]);
            $this->provider($subject, $member->email);
            $this->withSession($this->flow())->get(route('google.callback'))->assertRedirect(route('login'));
            $this->assertGuest();
        }
        $admin = User::factory()->create(['role'=>'superadmin','status'=>'active']);
        UserIdentity::query()->create(['user_id'=>$admin->id, 'provider'=>'google', 'provider_subject'=>'admin-sub']);
        $this->provider('admin-sub', $admin->email);
        $this->withSession($this->flow())->get(route('google.callback'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_google_email_must_be_verified(): void
    {
        $this->ready();
        $this->provider(verified: false);
        $this->withSession($this->flow())->get(route('google.callback'))->assertRedirect(route('login'));
        $this->assertDatabaseCount('users', 0);
    }

    public function test_onboarding_legal_gate_is_checked_again_at_submit(): void
    {
        $this->ready();
        $this->provider();
        $this->withSession($this->flow())->get(route('google.callback'));
        $pending = session('siapegawai_google_pending');
        config()->set('siapegawai.privacy_approved', false);
        $this->post(route('google.onboarding.store'), [
            'nonce'=>$pending['nonce'],'name'=>'Peserta Aman','whatsapp_number'=>'081234567890','consent'=>'1',
        ])->assertRedirect(route('login'))->assertSessionHas('google_feedback.code','registration_closed');
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('user_identities', 0);
    }

    public function test_link_requires_authenticated_active_participant_same_email_and_valid_password(): void
    {
        $this->ready();
        $user = User::factory()->create([
            'email'=>'link@example.test', 'password'=>Hash::make('PasswordValid1234'),
            'role'=>'participant','status'=>'active',
        ]);
        $this->post(route('google.link'), ['password'=>'PasswordValid1234'])->assertRedirect(route('login'));
        $this->actingAs($user)->post(route('google.link'), ['password'=>'wrong'])->assertSessionHasErrors('password');
        $this->assertDatabaseCount('user_identities', 0);
        $this->provider('link-sub', 'other@example.test');
        $this->withSession($this->flow('link', $user->id))->get(route('google.callback'))
            ->assertRedirect(route('member.profile.edit'));
        $this->assertDatabaseCount('user_identities', 0);
        $this->provider('link-sub', 'link@example.test');
        $this->withSession($this->flow('link', $user->id))->get(route('google.callback'))
            ->assertRedirect(route('member.profile.edit'))->assertSessionHas('google_link_feedback');
        $this->assertDatabaseHas('user_identities', ['user_id'=>$user->id,'provider'=>'google','provider_subject'=>'link-sub']);
        $this->assertDatabaseMissing('access_grants', ['user_id'=>$user->id,'grant_type'=>'member_plan']);
    }
}
