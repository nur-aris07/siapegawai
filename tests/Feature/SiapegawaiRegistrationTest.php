<?php

namespace Tests\Feature;

use App\Models\AccessGrant;
use App\Models\AuditLog;
use App\Models\ManualConfirmation;
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
        config()->set('siapegawai.whatsapp_number', '6281234567890');
    }

    public function test_registration_is_closed_by_default_without_approved_policy_and_whatsapp(): void
    {
        config()->set('siapegawai.registration_enabled', false);
        $this->get('/daftar')->assertOk()->assertSee('Registrasi belum dibuka');
        $this->post('/daftar', [
            'name' => 'Peserta Uji', 'email' => 'test@example.com', 'whatsapp_number' => '081234567890',
            'password' => 'PasswordRahasia1234', 'password_confirmation' => 'PasswordRahasia1234',
            'consent' => '1',
        ])->assertStatus(503);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_guest_registers_pending_only_and_receives_whatsapp_link(): void
    {
        $this->openRegistration();
        $response = $this->post('/daftar', [
            'name' => 'Peserta Uji', 'email' => 'TEST@example.com', 'whatsapp_number' => '081234567890',
            'password' => 'PasswordRahasia1234', 'password_confirmation' => 'PasswordRahasia1234',
            'consent' => '1', 'role' => 'superadmin', 'status' => 'active',
        ]);
        $response->assertStatus(303)->assertRedirect(route('registration.whatsapp'));
        $this->assertDatabaseHas('users', ['email' => 'test@example.com', 'role' => 'participant', 'status' => 'pending']);
        $this->assertDatabaseCount('registration_requests', 1);
        $this->assertDatabaseHas('consent_records', ['policy_version' => '2026-11-approved']);
        $this->get('/pendaftaran/lanjut-whatsapp')->assertOk()->assertSee('wa.me/6281234567890', false);
        $this->get('/dashboard')->assertRedirect('/aktivasi');
        $this->get('/admin')->assertForbidden();
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
