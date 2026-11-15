<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiapegawaiVisualLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_keep_v4_shell_and_task_hierarchy(): void
    {
        $this->get('/')->assertOk()->assertSee('shell-public')->assertSee('logo-accent.svg')
            ->assertSee('KENALAN DENGAN SIAPEGAWAI')->assertSee('Nara, Bima, dan Laras');
        $this->get('/bank-soal')->assertOk()->assertSee('bank-controls')->assertSee('bank-item');
        $this->get('/latihan-gratis/kerjakan')->assertOk()->assertSee('exam-heading')->assertSee('exam-shell');
        $this->get('/kelas')->assertOk()->assertSee('class-rows compact');
        $this->get('/paket')->assertOk()->assertSee('available-package');
    }

    public function test_login_and_registration_use_account_shell(): void
    {
        $this->get('/login')->assertOk()->assertSee('shell-account')->assertDontSee('site-nav');
        // Neither the development .env nor another feature test decides this visual gate.
        // Test both supported states without changing the actual registration policy.
        config()->set('siapegawai.registration_enabled', false);
        config()->set('siapegawai.privacy_approved', false);
        config()->set('siapegawai.terms_approved', false);
        config()->set('siapegawai.privacy_version', 'DRAFT');
        $this->get('/daftar')->assertOk()->assertSee('shell-account')
            ->assertSee('Registrasi belum dibuka')->assertDontSee('site-nav');

        config()->set('siapegawai.registration_enabled', true);
        config()->set('siapegawai.privacy_approved', true);
        config()->set('siapegawai.terms_approved', true);
        config()->set('siapegawai.privacy_version', '2026-11-approved');
        $this->get('/daftar')->assertOk()->assertSee('shell-account')
            ->assertSee('Daftar GRATIS')->assertDontSee('site-nav');
    }

    public function test_flash_text_is_escaped_in_toast_source(): void
    {
        $this->withSession(['success' => '<script>alert(1)</script>'])->get('/login')
            ->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_participant_workspace_does_not_render_public_header(): void
    {
        $participant = User::factory()->create(['role' => 'participant', 'status' => 'active']);
        $this->actingAs($participant)->get('/dashboard')->assertOk()
            ->assertSee('shell-participant')->assertDontSee('site-footer');
    }

    public function test_admin_workspace_does_not_render_public_header(): void
    {
        $admin = User::factory()->create(['role' => 'superadmin', 'status' => 'active']);
        $this->actingAs($admin)->get('/admin')->assertOk()
            ->assertSee('shell-admin')->assertDontSee('site-nav')->assertDontSee('site-footer')
            ->assertSee('Registrasi menunggu keputusan');
    }
}
