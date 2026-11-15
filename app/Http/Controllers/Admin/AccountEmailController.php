<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AccountStatusMail;
use App\Models\AccountEmailOutbox;
use App\Models\AuditLog;
use App\Services\AccountEmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Throwable;

class AccountEmailController extends Controller
{
    private function guard(Request $request): void { abort_unless($request->user()?->role === 'superadmin' && $request->user()->status === 'active', 403); }
    private function smtpReady(): bool
    {
        return config('mail.default') === 'smtp'
            && filled(config('mail.mailers.smtp.host'))
            && filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL)
            && ! str_ends_with(strtolower((string) config('mail.from.address')), '@example.com');
    }
    public function index(Request $request, AccountEmailService $service): View
    {
        $this->guard($request);
        $table = Schema::hasTable('account_email_outbox');
        $heartbeat = base_path('.siapegawai-backup/notify-email-01-activation/worker-heartbeat.json');
        $workerSeen = is_file($heartbeat) && filemtime($heartbeat) >= time() - 120;
        $counts = $table ? AccountEmailOutbox::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total','status')->toArray() : [];
        return view('admin.emails.index', [
            'smtpReady'=>$this->smtpReady(), 'queueReady'=>config('queue.default') === 'database',
            'sendEnabled'=>config('siapegawai.account_email_enabled') === true,
            'testRecipientReady'=>filter_var(config('siapegawai.smtp_test_recipient'), FILTER_VALIDATE_EMAIL) !== false,
            'fromReady'=>filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL) !== false,
            'tableReady'=>$table, 'counts'=>$counts, 'serviceReady'=>$service->ready(), 'workerSeen'=>$workerSeen,
            'items'=>$table ? AccountEmailOutbox::query()->select(['id','event_key','event_type','status','attempts','failure_code','created_at','sent_at'])->latest('id')->limit(20)->get() : collect(),
        ]);
    }
    public function preview(Request $request, string $kind): View
    {
        $this->guard($request);
        abort_unless(in_array($kind, [...AccountEmailService::TYPES, 'smtp_test'], true), 404);
        $mail = new AccountStatusMail($kind,'Peserta UAT');
        return view('emails.account-status', [
            'headline'=>AccountStatusMail::contentFor($kind)[0],
            'messageText'=>AccountStatusMail::contentFor($kind)[1],
            'displayName'=>'Peserta UAT', 'homeUrl'=>rtrim((string) config('app.url'), '/').'/login',
        ]);
    }
    public function test(Request $request): RedirectResponse
    {
        $this->guard($request);
        $recipient = (string) config('siapegawai.smtp_test_recipient');
        if (! $this->smtpReady() || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return back()->with('warning', 'SMTP atau alamat penerima UAT belum siap. Isi MAIL_* dan SIAPEGAWAI_SMTP_TEST_RECIPIENT melalui .env terlebih dahulu.');
        }
        try {
            Mail::mailer('smtp')->to($recipient)->send(new AccountStatusMail('smtp_test','Penguji SMTP'));
            AuditLog::query()->create(['actor_id'=>$request->user()->id,'action'=>'email.smtp_test_accepted',
                'subject_type'=>'user','subject_id'=>$request->user()->id,
                'before_summary'=>null,'after_summary'=>['transport'=>'smtp','result'=>'accepted'],'created_at'=>now()]);
            return back()->with('success', 'Server SMTP menerima pesan uji, bukan bukti masuk Inbox. Periksa Inbox, Spam, dan Promosi pada mailbox UAT sebelum mengirim uji ulang.');
        } catch (Throwable $e) {
            AuditLog::query()->create(['actor_id'=>$request->user()->id,'action'=>'email.smtp_test_failed',
                'subject_type'=>'user','subject_id'=>$request->user()->id,
                'before_summary'=>null,'after_summary'=>['transport'=>'smtp','result'=>'failed'],'created_at'=>now()]);
            return back()->with('warning', 'Pengiriman SMTP gagal. Periksa host, port, TLS, autentikasi, dan izin pengirim di server. Tidak ada perubahan akun.');
        }
    }
    /**
     * Deliberate, bounded resend of an already SMTP-accepted *account status* email.
     * Never silently replay an accepted message. Every resend has a fresh immutable outbox row.
     * Nothing here resends password tokens or admin invitations.
     */
    public function resend(Request $request, AccountEmailOutbox $outbox, AccountEmailService $service): RedirectResponse
    {
        $this->guard($request);
        if (! $request->boolean('checked_mailbox')) {
            return back()->with('warning', 'Periksa Inbox, Spam, dan Promosi terlebih dahulu, lalu centang konfirmasi pemeriksaan untuk meminta kirim ulang.');
        }
        if (! $service->ready()) {
            return back()->with('warning', 'Pengiriman otomatis belum siap. Pastikan migration outbox, SMTP dan queue siap terlebih dahulu.');
        }
        $result = DB::transaction(function () use ($outbox, $request): array {
            $original = AccountEmailOutbox::query()->whereKey($outbox->id)->lockForUpdate()->first();
            if (! $original || $original->status !== 'sent'
                || ! str_starts_with((string) $original->event_key, 'account:')
                || ! in_array($original->event_type, AccountEmailService::TYPES, true)) {
                return ['status'=>'unavailable'];
            }
            $user = User::query()->whereKey($original->user_id)->first();
            if (! $user || $user->role !== 'participant'
                || ! hash_equals(mb_strtolower((string) $user->email), mb_strtolower((string) $original->recipient))) {
                return ['status'=>'changed'];
            }
            $resends = AccountEmailOutbox::query()
                ->where('event_key', 'like', 'resend:'.$original->id.':%')
                ->orderByDesc('id')->get();
            if ($resends->count() >= 2) return ['status'=>'limit'];
            $last = $resends->first();
            $lastTime = $last?->created_at ?? $original->sent_at ?? $original->created_at;
            if ($lastTime && $lastTime->greaterThan(now()->subMinutes(30))) return ['status'=>'cooldown'];
            // Never automatically retry an ambiguous pending/processing/failed resend.
            if ($last && $last->status !== 'sent') return ['status'=>'review'];
            $row = AccountEmailOutbox::query()->create([
                'event_key'=>'resend:'.$original->id.':'.($resends->count() + 1),
                'user_id'=>$original->user_id,
                'event_type'=>$original->event_type,
                'recipient'=>$original->recipient,
                'status'=>'pending',
            ]);
            AuditLog::query()->create([
                'actor_id'=>$request->user()->id, 'action'=>'email.account_resend_requested',
                'subject_type'=>'user', 'subject_id'=>$user->id,
                'before_summary'=>null,
                'after_summary'=>['outbox_id'=>$original->id,'resend_id'=>$row->id,'result'=>'queued_intent'],
                'created_at'=>now(),
            ]);
            // Database queue dispatch after successful transaction commit only.
            DB::afterCommit(function () use ($row): void {
                app(AccountEmailService::class)->enqueueSafely((int) $row->id);
            });
            return ['status'=>'queued'];
        }, 3);
        return back()->with($result['status'] === 'queued' ? 'success' : 'warning', match($result['status']) {
            'queued' => 'Permintaan kirim ulang dicatat. Email menunggu worker, belum berarti diterima SMTP atau masuk Inbox.',
            'cooldown' => 'Kirim ulang belum tersedia. Tunggu setidaknya 30 menit sejak pengiriman terakhir, lalu periksa Inbox, Spam, dan Promosi.',
            'limit' => 'Batas dua kali kirim ulang telah tercapai. Periksa reputasi pengirim dan hubungi operator; jangan mengirim berulang.',
            'review' => 'Permintaan sebelumnya masih pending atau perlu pemeriksaan operator. Jangan antrekan duplikat.',
            'changed' => 'Alamat email akun telah berubah atau akun bukan peserta; pengiriman ulang ditolak.',
            default => 'Hanya notifikasi akun asli dengan status diterima SMTP yang dapat dikirim ulang.',
        });
    }

    public function retry(Request $request, AccountEmailOutbox $outbox, AccountEmailService $service): RedirectResponse
    {
        $this->guard($request);
        if (! $service->ready()) return back()->with('warning', 'Pengiriman akun belum siap. Periksa SMTP, worker, migration, dan feature flag.');
        // Manual retries only for pending items; not for ambiguous exhausted attempts.
        if ($outbox->status !== 'pending' || $outbox->attempts !== 0) return back()->with('warning', 'Item ini sudah diproses atau memerlukan pemeriksaan operator, bukan pengiriman ulang otomatis.');
        $ok = $service->enqueueSafely((int) $outbox->id);
        return back()->with($ok ? 'success':'warning', $ok ? 'Permintaan dikirim ke antrean; belum berarti email terkirim.' : 'Antrean gagal menerima permintaan. Item tetap pending.');
    }
}
