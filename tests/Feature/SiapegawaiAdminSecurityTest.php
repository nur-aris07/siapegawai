<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class SiapegawaiAdminSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role = 'superadmin'): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => 'active',
            'password' => 'PasswordAwal1234',
        ]);
    }

    public function test_security_page_is_for_active_admins_only_and_has_no_otp(): void
    {
        $root = $this->admin();
        $this->get(route('admin.security.edit'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role'=>'participant','status'=>'active']))
            ->get(route('admin.security.edit'))->assertForbidden();
        $this->actingAs($root)->get(route('admin.security.edit'))
            ->assertOk()->assertSee('Ganti kata sandi')->assertDontSee('OTP');
        $this->actingAs($this->admin('operational_admin'))
            ->get(route('admin.security.edit'))->assertOk();
    }

    public function test_wrong_current_password_must_not_change_credentials_or_create_audit(): void
    {
        $root = $this->admin();
        $this->actingAs($root)->post(route('admin.security.password'), [
            'current_password' => 'SALAH1234567890',
            'password' => 'PasswordBaru1234',
            'password_confirmation' => 'PasswordBaru1234',
        ])->assertSessionHasErrors('current_password');
        $this->assertSame(0, (int) $root->fresh()->admin_security_version);
        $this->assertTrue(Hash::check('PasswordAwal1234', $root->fresh()->password));
        $this->assertSame(0, AuditLog::where('action','admin.password_changed')->count());
    }

    public function test_password_change_rejects_stale_admin_sessions_and_keeps_audit_free_of_passwords(): void
    {
        $root = $this->admin();
        $this->actingAs($root)->post(route('admin.security.password'), [
            'current_password' => 'PasswordAwal1234',
            'password' => 'PasswordBaru1234',
            'password_confirmation' => 'PasswordBaru1234',
        ])->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertSame(1, (int) $root->fresh()->admin_security_version);
        $this->assertTrue(Hash::check('PasswordBaru1234', $root->fresh()->password));
        $entry = AuditLog::where('action', 'admin.password_changed')->firstOrFail();
        $this->assertSame($root->id, (int) $entry->actor_id);
        $this->assertStringNotContainsString('PasswordBaru1234', json_encode($entry->toArray()));
        $this->assertStringNotContainsString('PasswordAwal1234', json_encode($entry->toArray()));

        // Simulate a second browser that still holds the original session version.
        $this->actingAs($root)->withSession(['siapegawai_admin_security_version' => 0])
            ->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_valid_admin_login_sets_session_version_and_records_success(): void
    {
        $root = $this->admin('operational_admin');
        $root->forceFill(['admin_security_version'=>3])->save();
        $this->post(route('login.store'), [
            'email'=>$root->email, 'password'=>'PasswordAwal1234',
        ])->assertRedirect(route('admin.dashboard'))
          ->assertSessionHas('siapegawai_admin_security_version', 3);
        $this->get(route('admin.dashboard'))->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'admin.login_success')->where('subject_id', $root->id)->count());
    }

    public function test_bad_admin_login_is_audited_without_password_or_raw_ip(): void
    {
        $admin = $this->admin();
        $this->post(route('login.store'), [
            'email' => $admin->email, 'password' => 'PasswordSalah1234',
        ])->assertSessionHasErrors('email');
        $log = AuditLog::where('action', 'admin.login_failed')->firstOrFail();
        $encoded = json_encode($log->toArray());
        $this->assertStringNotContainsString('PasswordSalah1234', $encoded);
        $this->assertStringNotContainsString('127.0.0.1', $encoded);
        $this->assertGuest();
    }

    public function test_password_reset_revokes_old_admin_sessions_without_mfa(): void
    {
        $admin = $this->admin();
        $token = Password::createToken($admin);
        $this->post(route('password.update'), [
            'email' => $admin->email,
            'token' => $token,
            'password' => 'PasswordReset1234',
            'password_confirmation' => 'PasswordReset1234',
        ])->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('PasswordReset1234', $admin->fresh()->password));
        $this->assertSame(1, (int) $admin->fresh()->admin_security_version);
        $this->assertSame(1, AuditLog::where('action', 'admin.password_reset')->where('subject_id', $admin->id)->count());
        $this->actingAs($admin)->withSession(['siapegawai_admin_security_version' => 0])
            ->get(route('admin.security.edit'))->assertRedirect(route('login'));
    }
}
