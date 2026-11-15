<?php

namespace Tests\Feature;

use App\Jobs\SendAccountEmail;
use App\Mail\AccountStatusMail;
use App\Models\AccountEmailOutbox;
use App\Models\User;
use App\Services\AccountEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SiapegawaiAccountEmailTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp(); $this->withoutVite();
        config()->set('mail.default','smtp');
        config()->set('mail.mailers.smtp.host','smtp.uattest.invalid');
        config()->set('mail.from.address','noreply@siapegawai.test');
        config()->set('queue.default','database');
        config()->set('siapegawai.account_email_enabled',true);
    }
    private function participant(): User { return User::factory()->create(['role'=>'participant','status'=>'active']); }
    private function superadmin(): User { return User::factory()->create(['role'=>'superadmin','status'=>'active']); }

    public function test_default_off_prevents_outbox_but_does_not_change_account(): void
    {
        config()->set('siapegawai.account_email_enabled',false);
        $user = $this->participant();
        app(AccountEmailService::class)->record($user,'free_registered');
        $this->assertDatabaseCount('account_email_outbox',0);
        $this->assertSame('active',$user->fresh()->status);
    }
    public function test_outbox_intent_is_unique_per_event_and_rollback_safe(): void
    {
        $user=$this->participant(); Queue::fake();
        \Illuminate\Support\Facades\DB::transaction(function() use ($user) {
            app(AccountEmailService::class)->record($user,'free_registered');
            app(AccountEmailService::class)->record($user,'free_registered');
        });
        $this->assertDatabaseCount('account_email_outbox',1);
        $this->assertDatabaseHas('account_email_outbox',['user_id'=>$user->id,'status'=>'pending']);
        // RefreshDatabase owns an outer transaction, so afterCommit dispatch is not asserted here.
        // The outbox intent itself is transaction-coupled and deduplicated; worker has a separate test.
        try { \Illuminate\Support\Facades\DB::transaction(function() use ($user) {
            app(AccountEmailService::class)->record($user,'google_registered');
            throw new \RuntimeException('intentional rollback');
        }); } catch (\RuntimeException $e) {}
        $this->assertDatabaseCount('account_email_outbox',1);
    }
    public function test_worker_marks_accepted_and_does_not_send_twice(): void
    {
        $user=$this->participant(); Queue::fake(); Mail::fake();
        app(AccountEmailService::class)->record($user,'free_registered');
        $row=AccountEmailOutbox::query()->firstOrFail();
        (new SendAccountEmail($row->id))->handle(app(AccountEmailService::class));
        $this->assertSame('sent',$row->fresh()->status);
        (new SendAccountEmail($row->id))->handle(app(AccountEmailService::class));
        Mail::assertSent(AccountStatusMail::class,1);
        $this->assertSame(1,$row->fresh()->attempts);
    }
    public function test_template_content_is_bounded_and_has_no_payment_claim(): void
    {
        $this->assertStringContainsString('Pejuang',AccountStatusMail::contentFor('free_registered')[1]);
        $this->assertStringNotContainsString('WhatsApp',AccountStatusMail::contentFor('google_registered')[1]);
        $this->assertStringNotContainsString('NIK',AccountStatusMail::contentFor('legacy_rejected')[1]);
        $html=view('emails.account-status',['headline'=>'Uji','messageText'=>'Aman','displayName'=>'<script>unsafe</script>','homeUrl'=>'https://siapegawai.test/login'])->render();
        $this->assertStringNotContainsString('<script>unsafe</script>',$html);
        $this->assertStringContainsString('&lt;script&gt;',$html);
    }
    public function test_smtp_ui_is_superadmin_only_and_preview_has_no_send(): void
    {
        Mail::fake();
        $this->get('/admin/email')->assertRedirect('/login');
        $this->actingAs($this->participant())->get('/admin/email')->assertForbidden();
        $this->actingAs(User::factory()->create(['role'=>'operational_admin','status'=>'active']))
            ->get('/admin/email')->assertForbidden();
        $admin=$this->superadmin(); $this->actingAs($admin);
        // Admin-session middleware may require session version, mirror existing login tests.
        $response=$this->get('/admin/email');
        $response->assertOk()->assertSee('Email dan SMTP')->assertSee('Worker email lokal')
            ->assertSee('Status ini bukan bukti pesan diterima Inbox.');
        Mail::assertNothingSent();
    }

    public function test_local_email_activation_and_worker_use_the_laravel_project_root(): void
    {
        // tools/notify-email is exactly two levels below the Laravel project root.
        // This catches the C-03 regression before the operator runs backup/migration.
        $activation = file_get_contents(base_path('tools/notify-email/activate.php'));
        $worker = file_get_contents(base_path('tools/notify-email/worker.php'));
        $this->assertIsString($activation);
        $this->assertIsString($worker);
        $this->assertStringContainsString('$root = dirname(__DIR__, 2);', $activation);
        $this->assertStringContainsString('$root = dirname(__DIR__, 2);', $worker);
        $this->assertStringNotContainsString('dirname(__DIR__, 3)', $activation);
        $this->assertStringNotContainsString('dirname(__DIR__, 3)', $worker);
        $this->assertStringContainsString("\$pipes, \$GLOBALS['root'], \$envVars", $activation);
    }

    public function test_local_mariadb_backup_tool_discovery_covers_native_runtime_without_bypassing_backup(): void
    {
        $activation = file_get_contents(base_path('tools/notify-email/activate.php'));
        $this->assertIsString($activation);
        $this->assertStringContainsString('function findDumpCandidates', $activation);
        $this->assertStringContainsString('function serverInstallRoots', $activation);
        $this->assertStringContainsString('@@GLOBAL.basedir', $activation);
        $this->assertStringContainsString('function availableDumpBinary', $activation);
        $this->assertStringContainsString('function missingDumpTool', $activation);
        // C-06 resolves MariaDB paths from the live server's basedir, not a literal folder.
        $this->assertStringContainsString('array_merge($serverRoots', $activation);
        $this->assertStringContainsString('findDumpCandidates($GLOBALS[\'root\'], serverInstallRoots())', $activation);
        $this->assertStringContainsString('mariadb-dump.exe', $activation);
        $this->assertStringContainsString('mysqldump.exe', $activation);
        $this->assertStringContainsString('if ($specified !== \'\') return [$specified];', $activation);
        $this->assertStringContainsString('if ($binary === null) missingDumpTool();', $activation);
        $this->assertStringContainsString('backupDatabase($folder,config(', $activation);
        $this->assertStringContainsString('if (!$table) {', $activation);
    }

    public function test_uat_checks_backup_executable_before_expensive_suite_and_never_skips_database_backup(): void
    {
        $script = file_get_contents(base_path('tools/notify-email/complete-uat.cmd'));
        $activation = file_get_contents(base_path('tools/notify-email/activate.php'));
        $this->assertIsString($script);
        $this->assertIsString($activation);
        $this->assertLessThan(
            strpos($script, 'artisan test tests'),
            strpos($script, 'activate.php --check'),
        );
        $this->assertStringContainsString('if ($binary === null) missingDumpTool();', $activation);
        $this->assertStringContainsString('backupDatabase($folder,config(', $activation);
        $this->assertStringContainsString('if (!$table) {', $activation);
    }

    public function test_smtp_delivery_guidance_is_visible_and_signup_does_not_claim_inbox(): void
    {
        $this->actingAs($this->superadmin())->get('/admin/email')
            ->assertOk()->assertSee('SPF')->assertSee('DKIM')->assertSee('DMARC')
            ->assertSee('Spam')->assertSee('Promosi');
        $html = view('emails.account-status', [
            'headline'=>'Uji', 'messageText'=>'Aman', 'displayName'=>'Peserta',
            'homeUrl'=>'https://siapegawai.test/login',
        ])->render();
        $this->assertStringContainsString('Spam/Promosi', $html);
    }

    public function test_superadmin_can_resend_smtp_accepted_status_only_with_confirmation_cooldown_and_limit(): void
    {
        Queue::fake();
        $user = $this->participant();
        $superadmin = $this->superadmin();
        $source = AccountEmailOutbox::query()->create([
            'event_key'=>'account:free_registered:'.$user->id,
            'user_id'=>$user->id,'recipient'=>$user->email,
            'event_type'=>'free_registered','status'=>'sent',
            'sent_at'=>now()->subHours(2),
        ]);
        $url = route('admin.account-emails.resend', $source);
        $this->actingAs($superadmin)->post($url, [])->assertRedirect()->assertSessionHas('warning');
        $this->assertDatabaseCount('account_email_outbox',1);
        $this->post($url,['checked_mailbox'=>'1'])->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('account_email_outbox',[
            'event_key'=>'resend:'.$source->id.':1','status'=>'pending',
            'recipient'=>$user->email,'event_type'=>'free_registered',
        ]);
        $this->post($url,['checked_mailbox'=>'1'])->assertRedirect()->assertSessionHas('warning');
        $this->assertDatabaseCount('account_email_outbox',2);
        $this->travel(31)->minutes();
        $first=AccountEmailOutbox::query()->where('event_key','resend:'.$source->id.':1')->firstOrFail();
        $first->update(['status'=>'sent','sent_at'=>now()]);
        $this->post($url,['checked_mailbox'=>'1'])->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('account_email_outbox',['event_key'=>'resend:'.$source->id.':2','status'=>'pending']);
        $this->travel(31)->minutes();
        $this->post($url,['checked_mailbox'=>'1'])->assertRedirect()->assertSessionHas('warning');
        $this->assertDatabaseCount('account_email_outbox',3);
    }

    public function test_resend_is_forbidden_to_participants_and_rejects_pending_changed_and_disabled(): void
    {
        Queue::fake();
        $user=$this->participant();
        $source=AccountEmailOutbox::query()->create([
            'event_key'=>'account:google_registered:'.$user->id,
            'user_id'=>$user->id,'recipient'=>$user->email,
            'event_type'=>'google_registered','status'=>'sent','sent_at'=>now()->subHours(2),
        ]);
        $url=route('admin.account-emails.resend',$source);
        $this->actingAs($user)->post($url,['checked_mailbox'=>'1'])->assertForbidden();
        $this->actingAs($this->superadmin());
        config()->set('siapegawai.account_email_enabled',false);
        $this->post($url,['checked_mailbox'=>'1'])->assertSessionHas('warning');
        config()->set('siapegawai.account_email_enabled',true);
        $source->update(['recipient'=>'mismatch@example.test']);
        $this->post($url,['checked_mailbox'=>'1'])->assertSessionHas('warning');
        $source->update(['recipient'=>$user->email,'status'=>'pending']);
        $this->post($url,['checked_mailbox'=>'1'])->assertSessionHas('warning');
        $this->assertDatabaseCount('account_email_outbox',1);
    }
}
