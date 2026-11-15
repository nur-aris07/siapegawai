<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PublicSiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SiapegawaiSiteSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        app(PublicSiteSettings::class)->clearCache();
    }

    private function admin(string $role = 'superadmin'): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'active']);
    }

    private function payload(): array
    {
        $settings = app(PublicSiteSettings::class);
        return array_merge($settings->defaults(), ['version' => $settings->version($settings->all())]);
    }

    public function test_superadmin_only_and_existing_schema(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('site_settings'));
        $this->get('/admin/website')->assertRedirect('/login');
        $this->actingAs($this->admin('participant'))->get('/admin/website')->assertForbidden();
        $this->actingAs($this->admin('operational_admin'))->get('/admin/website')->assertForbidden();
        $this->actingAs($this->admin())->get('/admin/website')->assertOk()->assertSee('Pengaturan Website');
        $this->assertDatabaseCount('site_settings', 0);
    }

    public function test_table_uses_yajra_controls_without_status_and_keeps_delete_disabled(): void
    {
        $this->actingAs($this->admin());
        $response = $this->get('/admin/website');
        $response->assertOk()->assertSee('site-settings-table', false)
            ->assertSee('data-site-settings-table', false)
            ->assertSee('site-settings-search', false)
            ->assertSee('Tambah pengaturan')->assertSee('Edit')->assertSee('Hapus')
            ->assertSee('disabled aria-label="Hapus Nama tampilan website tidak tersedia"', false)
            ->assertDontSee('<th scope="col">Status</th>', false)
            ->assertDontSee('Belum diisi');
        foreach (PublicSiteSettings::FIELDS as $meta) $response->assertSee($meta['label']);
        $this->assertDatabaseCount('site_settings', 0);
    }

    public function test_yajra_ajax_listing_is_only_public_whitelist_and_custom(): void
    {
        $this->actingAs($this->admin());
        DB::table('site_settings')->insert([
            ['key' => 'smtp_password', 'value' => 'never-reveal-me', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'custom_judul_info', 'value' => 'Info resmi', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->get('/admin/website?draw=1&start=0&length=10');
        $response->assertOk()->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
        $response->assertJsonPath('recordsTotal', 9)->assertDontSee('never-reveal-me');
        $this->get('/admin/website')->assertOk()->assertSee('custom_judul_info')->assertDontSee('smtp_password');
    }

    public function test_modal_editor_preserves_public_whitelist_preview_and_does_not_write(): void
    {
        $this->actingAs($this->admin());
        $this->get('/admin/website?setting=support_email')->assertOk()
            ->assertSee('id="site-settings-dialog"', false)
            ->assertSee('data-open-on-load', false)
            ->assertSee('name="support_email"', false)
            ->assertSee('type="hidden" name="site_name"', false);
        $this->get('/admin/website?create=1')->assertOk()
            ->assertSee('name="custom_label"', false)->assertSee('name="custom_value"', false);
        $this->get('/admin/website?setting=mail_password')->assertOk()
            ->assertDontSee('id="site-settings-dialog"', false);
        $payload = $this->payload();
        $payload['support_email'] = 'help@example.org';
        $this->post('/admin/website/pratinjau', $payload)->assertOk()
            ->assertSee('Ringkasan perubahan')->assertSee('name="confirmed"', false);
        $this->assertDatabaseCount('site_settings', 0);
    }

    public function test_delete_site_setting_has_no_endpoint(): void
    {
        $this->actingAs($this->admin());
        $this->delete('/admin/website')->assertStatus(405);
        $this->assertDatabaseCount('site_settings', 0);
    }

    public function test_preview_does_not_write_settings_or_audit(): void
    {
        $this->actingAs($this->admin());
        $payload = $this->payload();
        $payload['site_name'] = 'Siapegawai UAT';
        $payload['support_email'] = 'bantuan@example.org';
        $this->post('/admin/website/pratinjau', $payload)->assertOk()->assertSee('Siapegawai UAT');
        $this->assertDatabaseCount('site_settings', 0);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'site_settings.updated']);
    }

    public function test_confirmed_save_audits_only_changed_fields_and_updates_public_footer(): void
    {
        $this->actingAs($this->admin());
        $payload = $this->payload();
        $payload['site_name'] = 'Siapegawai UAT';
        $payload['support_email'] = 'support@example.org';
        $payload['social_instagram'] = 'https://www.instagram.com/siapegawai/';
        $payload['confirmed'] = '1';
        $this->put('/admin/website', $payload)->assertRedirect('/admin/website');
        $this->assertDatabaseHas('site_settings', ['key' => 'site_name', 'value' => 'Siapegawai UAT']);
        $this->assertDatabaseHas('site_settings', ['key' => 'support_email', 'value' => 'support@example.org']);
        $this->assertSame(3, DB::table('audit_logs')->where('action','site_settings.updated')->count());
        $this->get('/')->assertOk()->assertSee('Siapegawai UAT')->assertSee('support@example.org');
        $this->get('/kontak')->assertOk()->assertSee('support@example.org');
        $this->put('/admin/website', array_merge($payload, ['version' => app(PublicSiteSettings::class)->version(app(PublicSiteSettings::class)->all())]))
            ->assertRedirect('/admin/website');
        $this->assertSame(3, DB::table('audit_logs')->where('action','site_settings.updated')->count());
    }

    public function test_unknown_keys_and_unsafe_urls_fail_without_changes(): void
    {
        $this->actingAs($this->admin());
        $payload = $this->payload();
        $this->post('/admin/website/pratinjau', $payload + ['mail_password' => 'pwn'])
            ->assertSessionHasErrors('settings');
        $this->post('/admin/website/pratinjau', array_merge($payload, ['support_url' => 'javascript:alert(1)']))
            ->assertSessionHasErrors('support_url');
        $this->post('/admin/website/pratinjau', array_merge($payload, ['social_instagram' => 'https://attacker.example.org/path']))
            ->assertSessionHasErrors('social_instagram');
        $this->post('/admin/website/pratinjau', array_merge($payload, ['site_name' => '<script>']))
            ->assertSessionHasErrors('site_name');
        $this->assertDatabaseCount('site_settings', 0);
    }

    public function test_stale_editor_and_missing_confirmation_rejected(): void
    {
        $this->actingAs($this->admin());
        $payload = $this->payload();
        $this->put('/admin/website', $payload)->assertSessionHasErrors('confirmed');
        $payload['confirmed'] = '1';
        $payload['site_name'] = 'Siapegawai UAT';
        $this->put('/admin/website', $payload)->assertRedirect('/admin/website');
        $payload['site_name'] = 'Overwrite by stale form';
        $this->put('/admin/website', $payload)->assertStatus(409);
        $this->assertDatabaseHas('site_settings', ['key' => 'site_name', 'value' => 'Siapegawai UAT']);
    }

    public function test_corrupt_stored_setting_falls_back_and_escapes_public_copy(): void
    {
        DB::table('site_settings')->insert(['key' => 'social_instagram', 'value' => 'javascript:alert(1)', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('site_settings')->insert(['key' => 'site_name', 'value' => '<img src=x onerror=alert(1)>', 'created_at' => now(), 'updated_at' => now()]);
        app(PublicSiteSettings::class)->clearCache();
        $this->assertSame('', app(PublicSiteSettings::class)->all()['social_instagram']);
        $this->assertSame('Siapegawai', app(PublicSiteSettings::class)->all()['site_name']);
        $this->get('/')->assertOk()->assertDontSee('<img src=x onerror=alert(1)>', false);
    }

    public function test_site_settings_cannot_modify_package_or_legal_flags(): void
    {
        $this->actingAs($this->admin());
        DB::table('site_settings')->insert(['key' => 'registration_enabled', 'value' => 'false', 'created_at' => now(), 'updated_at' => now()]);
        $payload = $this->payload();
        $payload['footer_note'] = 'Siapegawai independen';
        $payload['confirmed'] = '1';
        $this->put('/admin/website', $payload)->assertRedirect('/admin/website');
        $this->assertDatabaseHas('site_settings', ['key' => 'registration_enabled', 'value' => 'false']);
        foreach (['plan_price', 'qq_entitlement', 'cat_access', 'class_access', 'legal_approved'] as $forbidden) {
            $this->post('/admin/website/pratinjau', $this->payload() + [$forbidden => 'true'])->assertSessionHasErrors('settings');
        }
    }
    private function customVersion(): string
    {
        $rows = DB::table('site_settings')->where('key', 'like', 'custom%')->pluck('value', 'key')->all();
        ksort($rows);
        return hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function customPayload(string $name = 'Judul informasi beranda', string $value = 'Selamat datang'): array
    {
        return ['mode' => 'custom_create', 'version' => $this->customVersion(), 'custom_label' => $name, 'custom_value' => $value];
    }

    public function test_creating_custom_setting_previews_then_persists_and_audits_without_public_render(): void
    {
        $this->actingAs($this->admin());
        $payload = $this->customPayload();
        $this->post('/admin/website/pratinjau', $payload)->assertOk()
            ->assertSee('custom_judul_informasi_beranda')->assertSee('Selamat datang')
            ->assertSee('name="confirmed"', false);
        $this->assertDatabaseCount('site_settings', 0);
        $this->put('/admin/website', $payload + ['confirmed' => '1'])->assertRedirect('/admin/website');
        $this->assertDatabaseHas('site_settings', ['key' => 'custom_judul_informasi_beranda', 'value' => 'Selamat datang']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'site_settings.custom_created']);
        $this->get('/admin/website')->assertOk()->assertSee('custom_judul_informasi_beranda');
        $this->get('/')->assertOk()->assertDontSee('Selamat datang');
    }

    public function test_custom_edit_uses_modal_version_check_and_noop_does_not_duplicate_audit(): void
    {
        $this->actingAs($this->admin());
        $this->put('/admin/website', $this->customPayload() + ['confirmed' => '1'])->assertRedirect('/admin/website');
        $oldVersion = $this->customVersion();
        $this->get('/admin/website?setting=custom_judul_informasi_beranda')->assertOk()
            ->assertSee('name="custom_key"', false)->assertSee('site-settings-dialog', false);
        $edit = ['mode' => 'custom_edit', 'version' => $oldVersion,
            'custom_key' => 'custom_judul_informasi_beranda', 'custom_value' => 'Informasi terbaru', 'confirmed' => '1'];
        $this->put('/admin/website', $edit)->assertRedirect('/admin/website');
        $this->assertDatabaseHas('site_settings', ['key' => 'custom_judul_informasi_beranda', 'value' => 'Informasi terbaru']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'site_settings.custom_updated']);
        $this->put('/admin/website', $edit)->assertStatus(409);
        $edit['version'] = $this->customVersion();
        $this->put('/admin/website', $edit)->assertRedirect('/admin/website');
        $this->assertSame(2, DB::table('audit_logs')->where('subject_type', 'site_setting')->count());
    }

    public function test_custom_settings_reject_unsafe_names_values_duplicate_and_foreign_keys(): void
    {
        // This is a matrix of 20 validation requests, not a throttle test.
        // POST preview (20/min) and PUT save (10/min) share an authenticated
        // request limiter key, so the real middleware can return HTTP 429
        // before these requests reach SiteSettingsController.
        // Disable ONLY the throttle middleware in this validation matrix.
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->actingAs($this->admin());
        foreach (['SMTP Password', 'OAuth Token', 'Harga Paket', 'Paket VIP', 'Biaya Langganan',
            'Kuota Harian', 'Tarif Bulanan', 'Metode Pembayaran', 'Billing Plan', 'Kata Sandi'] as $name) {
            $payload = $this->customPayload($name);
            $this->post('/admin/website/pratinjau', $payload)
                ->assertRedirect()->assertSessionHasErrors('settings');
            // Direct PUT must enforce identical validation if preview was skipped.
            $this->put('/admin/website', $payload + ['confirmed' => '1'])
                ->assertRedirect()->assertSessionHasErrors('settings');
        }
        $this->assertDatabaseCount('site_settings', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->post('/admin/website/pratinjau', $this->customPayload('Pojok Info', '<script>alert(1)</script>'))
            ->assertSessionHasErrors('custom_value');
        $this->post('/admin/website/pratinjau', $this->customPayload() + ['package_price' => '1'])
            ->assertSessionHasErrors('settings');
        $this->put('/admin/website', $this->customPayload())->assertSessionHasErrors('confirmed');
        $this->put('/admin/website', $this->customPayload() + ['confirmed' => '1'])->assertRedirect('/admin/website');
        $this->post('/admin/website/pratinjau', $this->customPayload())->assertSessionHasErrors('custom_label');
        $this->assertDatabaseMissing('site_settings', ['key' => 'smtp_password']);
    }

    public function test_site_settings_save_throttle_blocks_eleventh_request(): void
    {
        // Actual route middleware is deliberately ENABLED in this test.
        // Invalid writes are chosen so no settings or audit rows are created.
        $this->actingAs($this->admin());
        $payload = $this->customPayload(); // Missing "confirmed" by design.
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->put('/admin/website', $payload)
                ->assertRedirect()->assertSessionHasErrors('confirmed');
        }
        $this->put('/admin/website', $payload)->assertStatus(429);
        $this->assertDatabaseCount('site_settings', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_custom_setting_changes_cannot_modify_whitelisted_public_or_sensitive_keys(): void
    {
        $this->actingAs($this->admin());
        DB::table('site_settings')->insert(['key' => 'registration_enabled', 'value' => 'false', 'created_at' => now(), 'updated_at' => now()]);
        $this->put('/admin/website', $this->customPayload() + ['confirmed' => '1'])->assertRedirect('/admin/website');
        $this->assertDatabaseHas('site_settings', ['key' => 'registration_enabled', 'value' => 'false']);
        $this->assertSame('Siapegawai', app(PublicSiteSettings::class)->all()['site_name']);
        $this->put('/admin/website', ['mode' => 'custom_edit', 'version' => $this->customVersion(),
            'custom_key' => 'site_name', 'custom_value' => 'Pwn', 'confirmed' => '1'])->assertSessionHasErrors('custom_key');
    }

    public function test_table_uses_progressively_enhanced_editor_and_aligned_toolbar(): void
    {
        $this->actingAs($this->admin());
        $response = $this->get('/admin/website');
        $response->assertOk()
            ->assertSee('class="site-settings-toolbar-actions"', false)
            ->assertSee('id="site-settings-create" data-site-settings-open-editor', false)
            ->assertSee('id="site-settings-editor-message"', false)
            ->assertSee('Lihat website')
            ->assertSee('data-site-settings-open-editor>Edit</a>', false)
            ->assertDontSee('id="site-settings-dialog"', false);
        // Server-rendered GET remains a working fallback for no-JS and deep links.
        $this->get('/admin/website?create=1')->assertOk()
            ->assertSee('id="site-settings-dialog"', false)
            ->assertSee('name="custom_label"', false);
        $this->get('/admin/website?setting=footer_note')->assertOk()
            ->assertSee('id="site-settings-dialog"', false)
            ->assertSee('name="footer_note"', false);
        $js = file_get_contents(resource_path('js/admin-site-settings.js'));
        $css = file_get_contents(resource_path('css/admin-v1.css'));
        $this->assertStringContainsString('dialog.showModal()', $js);
        $this->assertStringContainsString("event.preventDefault()", $js);
        $this->assertStringContainsString("headers: { Accept: 'text/html' }", $js);
        $this->assertStringContainsString('site-settings-panel { width: 100%; max-width: none; }', $css);
    }

}
