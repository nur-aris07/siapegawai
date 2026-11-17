<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdminNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SiapegawaiAdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function admin(string $role = 'superadmin'): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'active']);
    }

    private function flatten(array $navigation): array
    {
        $result = [];
        foreach ($navigation as $entry) {
            foreach (($entry['type'] === 'group' ? $entry['items'] : [$entry]) as $item) {
                $result[$item['route']] = $item;
            }
        }
        return $result;
    }

    public function test_every_global_destination_exists_as_parameter_free_get_route(): void
    {
        $items = $this->flatten(AdminNavigation::forUser($this->admin(), 'admin.dashboard'));
        foreach ($items as $name => $item) {
            $this->assertTrue(Route::has($name), $name);
            $route = Route::getRoutes()->getByName($name);
            $this->assertContains('GET', $route->methods(), $name);
            $this->assertSame([], $route->parameterNames(), $name);
            $this->assertNotEmpty(route($name));
        }
        $this->assertArrayNotHasKey('admin.participants.create', $items);
        $this->assertArrayNotHasKey('admin.class-practices.create', $items);
    }

    public function test_operational_admin_only_sees_allowed_categories_and_links(): void
    {
        $items = AdminNavigation::forUser($this->admin('operational_admin'), 'questions.import.show');
        $routes = $this->flatten($items);
        foreach (['admin.packages.index', 'admin.qq-quota.index', 'admin.site-settings.index', 'admin.account-emails.index'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $routes);
        }
        $this->assertArrayHasKey('questions.import.index', $routes);
        $this->assertTrue($routes['questions.import.index']['active']);
        $this->assertSame(['dashboard', 'people', 'questions', 'learning', 'security'], array_column($items, 'id'));
    }

    public function test_superadmin_has_all_real_categories_and_smtp_under_system(): void
    {
        $items = AdminNavigation::forUser($this->admin(), 'admin.account-emails.preview');
        $this->assertSame(['dashboard', 'people', 'questions', 'learning', 'packages', 'system', 'security'], array_column($items, 'id'));
        $system = collect($items)->firstWhere('id', 'system');
        $this->assertTrue($system['active']);
        $this->assertSame(['admin.site-settings.index', 'admin.account-emails.index'], array_column($system['items'], 'route'));
        $this->assertTrue($system['items'][1]['active']);
    }

    public function test_route_families_resolve_exactly_one_active_destination(): void
    {
        $cases = [
            'admin.dashboard'=>'admin.dashboard',
            'registration.index'=>'registration.index',
            'admin.participants.show'=>'admin.participants.index',
            'questions.admin.edit'=>'questions.admin.index',
            'questions.import.show'=>'questions.import.index',
            'subjects.admin.topics.update'=>'subjects.admin.index',
            'tryouts.admin.edit'=>'tryouts.admin.index',
            'admin.quick-quizzes.edit'=>'admin.quick-quizzes.index',
            'admin.classes.monitoring.show'=>'admin.classes.index',
            'admin.class-practices.create'=>'admin.classes.index',
            'admin.class-practices.edit'=>'admin.classes.index',
            'admin.packages.requests'=>'admin.packages.index',
            'admin.qq-quota.index'=>'admin.qq-quota.index',
            'admin.site-settings.preview'=>'admin.site-settings.index',
            'admin.account-emails.preview'=>'admin.account-emails.index',
            'admin.security.edit'=>'admin.security.edit',
        ];
        $user = $this->admin();
        foreach ($cases as $current => $expected) {
            $active = collect($this->flatten(AdminNavigation::forUser($user, $current)))
                ->filter(fn ($item) => $item['active'])->keys()->all();
            $this->assertSame([$expected], $active, $current);
        }
    }

    public function test_rendered_sidebar_has_single_current_link_and_native_disclosures(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));
        $response->assertOk()
            ->assertSee('data-admin-nav', false)
            ->assertSee('data-admin-nav-group', false)
            ->assertSee('admin-nav-collapse', false)
            ->assertSee('admin-menu-toggle', false)
            ->assertSee('admin-nav-overlay', false)
            ->assertSee('Impor CSV')
            ->assertSee('Email &amp; SMTP', false)
            ->assertSee('Lihat website publik');
        $content = $response->getContent();
        $this->assertSame(1, substr_count($content, 'aria-current="page"'));
        $this->assertStringNotContainsString('TAHAP BERIKUTNYA', $content);
        $this->assertStringNotContainsString('CAT peserta</span>', $content);
    }

    public function test_operational_role_does_not_render_superadmin_destinations(): void
    {
        $response = $this->actingAs($this->admin('operational_admin'))->get(route('admin.dashboard'));
        $response->assertOk()->assertSee('Registrasi Peserta')->assertSee('Impor CSV')
            ->assertDontSee('Pengaturan Website')->assertDontSee('Email &amp; SMTP', false)
            ->assertDontSee('Katalog Paket')->assertDontSee('Manfaat Quiz Kilat');
    }

    public function test_direct_endpoint_permission_is_unchanged(): void
    {
        $operational = $this->admin('operational_admin');
        $this->actingAs($operational);
        foreach (['admin.account-emails.index', 'admin.site-settings.index', 'admin.packages.index', 'admin.qq-quota.index', 'admin.participants.create'] as $name) {
            $this->get(route($name))->assertForbidden();
        }
        $this->get(route('admin.security.edit'))->assertOk();
        $this->get(route('questions.import.index'))->assertOk();
    }

    public function test_participant_cannot_access_admin_workspace_directly(): void
    {
        $user = User::factory()->create(['role'=>'participant','status'=>'active']);
        $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
        $this->get(route('questions.import.index'))->assertForbidden();
    }

    public function test_navigation_asset_is_external_and_legacy_question_initializer_removed(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/admin.blade.php'));
        $legacy = file_get_contents(resource_path('js/admin-questions.js'));
        $module = file_get_contents(resource_path('js/admin-navigation.js'));
        $this->assertStringContainsString('resources/js/admin-navigation.js', $layout);
        $this->assertStringNotContainsString('admin-menu-toggle', $legacy);
        $this->assertStringContainsString('matchMedia', $module);
        $this->assertStringContainsString('setBackgroundInert', $module);
        $this->assertStringNotContainsString('onclick=', $layout);
        $this->assertStringNotContainsString('<script>', $layout);
        $this->assertStringNotContainsString('style=', $layout);
    }

    public function test_top_level_navigation_icons_are_distinct_and_defined(): void
    {
        $navigation = AdminNavigation::definition();
        $icons = array_column($navigation, 'icon');
        $this->assertCount(7, $icons);
        $this->assertCount(7, array_unique($icons));
        $this->assertSame([
            'chart-bar', 'users', 'book', 'graduation-cap', 'package', 'settings', 'shield-check',
        ], $icons);
        $sprite = file_get_contents(resource_path('views/components/icon-sprite.blade.php'));
        foreach ($icons as $icon) {
            $this->assertStringContainsString('id="ic-'.$icon.'"', $sprite);
        }
    }

    public function test_mobile_navigation_contains_accessible_account_and_post_logout(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));
        $response->assertOk()->assertSee('admin-nav-account', false)
            ->assertSee('Akun masuk')->assertSee('Keluar akun')
            ->assertSee('admin-v1-header-logout', false);
        $html = $response->getContent();
        $this->assertSame(2, substr_count($html, 'action="'.route('logout').'"'));
        $this->assertSame(2, preg_match_all('/<form\b[^>]*method="POST"[^>]*action="'.preg_quote(route('logout'), '/').'"/', $html));
        $style = file_get_contents(resource_path('css/admin-v1.css'));
        $this->assertStringContainsString('.admin-v1 .admin-nav-account { display: none; }', $style);
        $this->assertStringContainsString('max-width: 760px', $style);
        $this->assertStringContainsString('.admin-v1 .admin-v1-header .admin-v1-header-logout { display: none; }', $style);
        $this->assertStringContainsString('.admin-v1 .admin-nav-account-logout:focus-visible', $style);
        $layout = file_get_contents(resource_path('views/layouts/admin.blade.php'));
        $this->assertStringNotContainsString('onclick=', $layout);
        $this->assertStringNotContainsString('<script>', $layout);
    }
}
