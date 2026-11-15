<?php

namespace Tests\Feature;

use App\Models\AccessGrant;
use App\Models\AuditLog;
use App\Models\ConsentRecord;
use App\Models\ManualConfirmation;
use App\Services\MemberPlanService;
use App\Services\QuickQuizQuotaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\RegistrationRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiapegawaiRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function openRegistration(): void
    {
        config()->set('siapegawai.registration_enabled', true);
        config()->set('siapegawai.privacy_approved', true);
        config()->set('siapegawai.terms_approved', true);
        config()->set('siapegawai.privacy_version', '2026-11-approved');
        // Official WA is intentionally absent; free registration must not depend on it.
        config()->set('siapegawai.whatsapp_number', '');
    }

    private function registrationInput(string $email = 'test@example.com'): array
    {
        return [
            'name' => 'Peserta Uji', 'email' => $email, 'whatsapp_number' => '081234567890',
            'password' => 'PasswordRahasia1234', 'password_confirmation' => 'PasswordRahasia1234',
            'consent' => '1',
        ];
    }
    public function test_registration_is_closed_by_default_and_fails_closed_for_unapproved_legal(): void
    {
        config()->set('siapegawai.registration_enabled', false);
        $this->get('/daftar')->assertOk()->assertSee('Registrasi belum dibuka');
        $this->post('/daftar', $this->registrationInput())->assertStatus(503);
        $this->assertDatabaseCount('users', 0);
        $this->openRegistration();
        foreach ([
            ['siapegawai.privacy_approved', false],
            ['siapegawai.terms_approved', false],
            ['siapegawai.privacy_version', 'DRAFT'],
            ['siapegawai.privacy_version', 'draft'],
            ['siapegawai.privacy_version', ''],
        ] as $scenario => [$key, $invalid]) {
            // Different simulated clients exercise each legal gate without exhausting the real 5/min throttle.
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.(101 + $scenario)]);
            $before = config($key);
            config()->set($key, $invalid);
            $this->post('/daftar', $this->registrationInput())->assertStatus(503);
            config()->set($key, $before);
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('registration_requests', 0);
        $this->assertDatabaseCount('consent_records', 0);
    }
    public function test_registration_throttle_still_limits_post_attempts(): void
    {
        // A single client still receives HTTP 429 on attempt 6; production throttle remains enabled.
        config()->set('siapegawai.registration_enabled', false);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.210']);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/daftar', $this->registrationInput())->assertStatus(503);
        }
        $this->post('/daftar', $this->registrationInput())->assertStatus(429);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('registration_requests', 0);
    }

    public function test_guest_registers_active_free_without_official_whatsapp_or_premium(): void
    {
        $this->openRegistration();
        $this->get('/daftar')->assertOk()->assertSee('Daftar GRATIS')->assertDontSee('Daftar &amp; lanjut WhatsApp');
        $input = $this->registrationInput('TEST@example.com');
        $input['role'] = 'superadmin';
        $input['status'] = 'suspended';
        $response = $this->post('/daftar', $input);
        $response->assertStatus(303)->assertRedirect(route('dashboard'));
        $user = User::query()->where('email', 'test@example.com')->sole();
        $this->assertSame('participant', $user->role);
        $this->assertSame('active', $user->status);
        $this->assertNotNull($user->activated_at);
        $this->assertNull($user->activated_by);
        $this->assertTrue(Auth::check());
        $this->assertSame($user->id, Auth::id());
        $this->assertNotSame('PasswordRahasia1234', $user->password);
        $this->assertDatabaseHas('registration_requests', [
            'user_id' => $user->id, 'status' => 'active', 'reviewed_by' => null, 'reviewed_at' => null,
        ]);
        $this->assertDatabaseHas('consent_records', [
            'user_id' => $user->id, 'policy_version' => '2026-11-approved', 'source' => 'web_registration',
        ]);
        $this->assertDatabaseHas('access_grants', [
            'user_id' => $user->id, 'grant_type' => 'member', 'granted_by' => null, 'status' => 'active',
        ]);
        $this->assertDatabaseMissing('access_grants', ['user_id' => $user->id, 'grant_type' => 'member_plan']);
        $this->assertDatabaseMissing('access_grants', ['user_id' => $user->id, 'grant_type' => 'qq_quota']);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => null, 'action' => 'registration.auto_activated', 'subject_id' => $user->id,
        ]);
        $this->assertDatabaseCount('manual_confirmations', 0);
        $this->assertSame('PEJUANG', app(MemberPlanService::class)->current($user)['plan']->code);
        $this->assertSame('POLICY_NOT_CONFIGURED', app(QuickQuizQuotaService::class)->remaining($user)['reason']);
        $this->get('/dashboard')->assertOk();
        $this->get('/pendaftaran/lanjut-whatsapp')->assertRedirect(route('dashboard'));
        $this->get('/admin')->assertForbidden();
        $this->get('/aktivasi')->assertOk()->assertDontSee('wa.me/', false);
    }
    public function test_auto_activated_registration_is_visible_to_admin_but_cannot_be_approved_again(): void
    {
        $this->openRegistration();
        $this->post('/daftar', $this->registrationInput())->assertRedirect(route('dashboard'));
        $user = User::query()->where('email', 'test@example.com')->sole();
        $admin = User::factory()->create(['role' => 'operational_admin', 'status' => 'active']);
        $this->actingAs($admin)->get(route('registration.show', $user))->assertOk()
            ->assertSee('Aktif otomatis')->assertSee('Registrasi gratis otomatis')
            ->assertDontSee('Ambil keputusan');
        $this->actingAs($admin)->post(route('registration.decide', $user), [
            'decision' => 'approve', 'admin_note' => 'Tidak boleh mengaktifkan dua kali.',
            'confirmed_external' => 1,
        ])->assertSessionHasErrors('decision');
        $this->assertDatabaseCount('manual_confirmations', 0);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'registration.approve', 'subject_id' => $user->id,
        ]);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'active']);
    }
    public function test_failed_registration_transaction_rolls_back_every_record_and_no_login(): void
    {
        $this->openRegistration();
        $failAudit = true;
        DB::listen(function ($event) use (&$failAudit): void {
            if ($failAudit && str_contains(strtolower($event->sql), 'audit_logs')) {
                throw new \RuntimeException('Simulated audit persistence failure');
            }
        });
        try {
            $this->post('/daftar', $this->registrationInput())->assertStatus(500);
        } finally {
            $failAudit = false;
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('registration_requests', 0);
        $this->assertDatabaseCount('consent_records', 0);
        $this->assertDatabaseCount('access_grants', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertGuest();
    }

    public function test_duplicate_email_does_not_create_second_registration(): void
    {
        $this->openRegistration();
        User::factory()->create(['email' => 'existing@example.com']);
        $this->from('/daftar')->post('/daftar', [
            'name' => 'Peserta Uji', 'email' => 'existing@example.com', 'whatsapp_number' => '081234567890',
            'password' => 'PasswordRahasia1234', 'password_confirmation' => 'PasswordRahasia1234', 'consent' => '1',
        ])->assertRedirect('/daftar')->assertSessionHasErrors('email');
        $this->assertDatabaseCount('registration_requests', 0);
    }

    public function test_admin_can_activate_once_with_reason_and_audit(): void
    {
        $admin = User::factory()->create(['role' => 'operational_admin', 'status' => 'active']);
        $participant = User::factory()->create(['role' => 'participant', 'status' => 'pending']);
        RegistrationRequest::create([
            'user_id' => $participant->id, 'reference_code' => 'SGP-ABC123ABC123',
            'status' => 'pending', 'submitted_at' => now(),
        ]);
        $url = route('registration.decide', $participant);
        $this->actingAs($admin)->post($url, [
            'decision' => 'approve', 'admin_note' => 'Sudah diverifikasi melalui pesan resmi.', 'confirmed_external' => 1,
        ])->assertRedirect(route('registration.show', $participant));
        $this->assertSame('active', $participant->fresh()->status);
        $this->assertSame(1, AccessGrant::where('user_id', $participant->id)->where('grant_type', 'member')->count());
        $this->assertSame(1, AuditLog::where('subject_id', $participant->id)->where('action', 'registration.approve')->count());
        $this->assertSame(1, ManualConfirmation::where('user_id', $participant->id)->count());
        $this->post($url, [
            'decision' => 'approve', 'admin_note' => 'Permintaan ulang seharusnya tidak boleh.', 'confirmed_external' => 1,
        ])->assertSessionHasErrors('decision');
        $this->assertSame(1, AccessGrant::where('user_id', $participant->id)->count());
    }

    public function test_participants_cannot_read_admin_json_even_if_ajax(): void
    {
        $participant = User::factory()->create(['role' => 'participant', 'status' => 'active']);
        $this->actingAs($participant)->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->get('/admin/registrasi')
            ->assertForbidden();
    }

    public function test_admin_index_serves_blade_and_json_at_the_same_url(): void
    {
        $admin = User::factory()->create(['role' => 'operational_admin', 'status' => 'active']);
        $this->actingAs($admin)->get('/admin/registrasi')->assertOk()->assertSee('Registrasi peserta');
        $this->actingAs($admin)->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get('/admin/registrasi?draw=1&start=0&length=10')
            ->assertOk()->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
    }
}
