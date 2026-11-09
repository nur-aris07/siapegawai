<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiapegawaiPublicNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_login_instead_of_private_progress_link(): void
    {
        foreach (['/', '/latihan-gratis/kerjakan', '/bank-soal'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('href="' . route('login') . '">Masuk</a>', false)
                ->assertDontSee('Progres saya');
        }
    }

    public function test_active_participant_gets_participant_dashboard_link(): void
    {
        $user = User::factory()->create(['role' => 'participant', 'status' => 'active']);

        $this->actingAs($user)->get('/latihan-gratis/kerjakan')
            ->assertOk()
            ->assertSee('href="' . route('dashboard') . '">Dashboard peserta</a>', false)
            ->assertDontSee('Panel admin');
    }

    public function test_pending_participant_gets_activation_status_link(): void
    {
        $user = User::factory()->create(['role' => 'participant', 'status' => 'pending']);

        $this->actingAs($user)->get('/bank-soal')
            ->assertOk()
            ->assertSee('href="' . route('registration.status') . '">Status aktivasi</a>', false)
            ->assertDontSee('Panel admin');
    }

    public function test_admin_gets_explicit_admin_panel_link(): void
    {
        $user = User::factory()->create(['role' => 'superadmin', 'status' => 'active']);

        $this->actingAs($user)->get('/latihan-gratis/kerjakan')
            ->assertOk()
            ->assertSee('href="' . route('admin.dashboard') . '">Panel admin</a>', false)
            ->assertDontSee('Progres saya');
    }
}
