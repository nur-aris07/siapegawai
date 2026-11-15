<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiapegawaiParticipantWorkspaceUiTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $role = 'participant', string $status = 'active'): User
    {
        return User::factory()->create(['role' => $role, 'status' => $status, 'name' => 'Peserta Uji']);
    }

    public function test_participant_header_has_five_learning_links_and_account_menu(): void
    {
        $html = $this->actingAs($this->member())->get(route('dashboard'))
            ->assertOk()->assertSee('shell-participant')->assertSee('participant-workspace-nav')
            ->assertSee('data-workspace-menu-toggle')->assertSee('data-workspace-account')->getContent();

        preg_match('/<nav id="participant-workspace-nav".*?<\/nav>/s', $html, $matches);
        $this->assertCount(1, $matches);
        $nav = $matches[0];
        $this->assertSame(5, substr_count($nav, '<a '));
        foreach (['Ringkasan', 'Tryout saya', 'Kelas saya', 'Quiz Kilat', 'Analisis kemampuan'] as $label) {
            $this->assertStringContainsString('>'.$label.'</a>', $nav);
        }
        foreach (['Demo SKD', 'Bank soal contoh', 'Katalog kelas', 'Profil', 'Keluar'] as $name) {
            $this->assertStringNotContainsString('>'.$name.'</a>', $nav);
        }
        $this->assertStringContainsString('method="POST" action="'.route('logout').'"', $html);
        $this->assertStringContainsString('type="hidden" name="_token"', $html);
        $this->assertStringContainsString('>Profil saya</a>', $html);
        $this->assertStringContainsString('>Keluar</button>', $html);
    }

    public function test_dashboard_retains_learning_routes_and_public_exploration(): void
    {
        $html = $this->actingAs($this->member())->get(route('dashboard'))
            ->assertOk()->assertSee('Lanjut belajar')
            ->assertSee('Jelajahi konten publik')->assertSee('Analisis Kemampuan')->getContent();

        foreach ([
            'member.tryouts.index', 'member.classes.index', 'member.quick-quizzes.index',
            'member.analytics.index', 'practice.intro', 'questions.index', 'page.kelas',
        ] as $routeName) {
            $this->assertStringContainsString(route($routeName), $html);
        }
        $this->assertStringContainsString('Buka Analisis Kemampuan', $html);
        $this->assertStringContainsString('template diterbitkan', $html);
    }

    public function test_active_navigation_is_correct_for_each_learning_domain(): void
    {
        $this->actingAs($this->member());
        foreach (['dashboard', 'member.tryouts.index', 'member.classes.index',
            'member.quick-quizzes.index', 'member.analytics.index'] as $routeName) {
            $html = $this->get(route($routeName))->assertOk()->getContent();
            preg_match('/<nav id="participant-workspace-nav".*?<\/nav>/s', $html, $matches);
            $this->assertCount(1, $matches);
            $this->assertSame(1, substr_count($matches[0], 'aria-current="page"'));
        }
    }

    public function test_guest_and_admin_cannot_use_private_workspace(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->actingAs($this->member('superadmin'))->get(route('dashboard'))->assertForbidden();
        $this->actingAs($this->member('participant', 'pending'))->get(route('dashboard'))
            ->assertRedirect();
    }

    public function test_workspace_javascript_is_local_vite_and_has_no_inline_handlers(): void
    {
        $html = $this->actingAs($this->member())->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('onclick=', $html);
        $this->assertStringNotContainsString('onkeydown=', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('member-workspace-nav.js', file_get_contents(base_path('vite.config.js')));
        $this->assertStringContainsString('resources/js/member-workspace-nav.js', file_get_contents(resource_path('views/layouts/workspace.blade.php')));
        $this->assertFileExists(resource_path('js/member-workspace-nav.js'));
    }

    public function test_public_and_admin_layouts_are_not_replaced(): void
    {
        $this->get('/')->assertOk()->assertSee('shell-public')->assertDontSee('data-workspace-account');
        $this->actingAs($this->member('superadmin'))->get(route('admin.dashboard'))
            ->assertOk()->assertSee('shell-admin')->assertDontSee('data-workspace-menu-toggle');
    }
}
