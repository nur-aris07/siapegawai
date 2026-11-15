<?php

namespace App\Services;

use App\Jobs\SendAccountEmail;
use App\Models\AccountEmailOutbox;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** Business state is authoritative; email delivery is never an account activation prerequisite. */
class AccountEmailService
{
    public const TYPES = ['free_registered','google_registered','invitation_activated','legacy_approved','legacy_rejected'];

    public function ready(): bool
    {
        $from = (string) config('mail.from.address');
        return config('siapegawai.account_email_enabled') === true
            && config('mail.default') === 'smtp'
            && config('queue.default') === 'database'
            && filled(config('mail.mailers.smtp.host'))
            && filter_var($from, FILTER_VALIDATE_EMAIL) !== false
            && ! str_ends_with(strtolower($from), '@example.com')
            && Schema::hasTable('account_email_outbox');
    }

    /** Called inside the SAME transaction as user mutation: rollback also cancels the intent. */
    public function record(User $user, string $event): void
    {
        if (! in_array($event, self::TYPES, true) || ! $this->ready()) return;
        if (! filter_var($user->email, FILTER_VALIDATE_EMAIL)) return;
        $key = 'account:'.$event.':'.$user->getKey();
        $row = AccountEmailOutbox::query()->firstOrCreate(['event_key'=>$key], [
            'user_id'=>$user->getKey(), 'event_type'=>$event, 'recipient'=>$user->email, 'status'=>'pending',
        ]);
        if (! $row->wasRecentlyCreated) return;
        // Callback executes only after the entire transaction commits. Queue outage MUST NOT roll back activation.
        DB::afterCommit(function () use ($row): void { $this->enqueueSafely((int) $row->getKey()); });
    }

    public function enqueueSafely(int $id): bool
    {
        try {
            SendAccountEmail::dispatch($id)->onConnection('database')->onQueue('account-emails')->afterCommit();
            return true;
        } catch (Throwable $e) {
            // Preserve pending outbox for operator recovery; never log SMTP secrets or raw exception.
            return false;
        }
    }
}
