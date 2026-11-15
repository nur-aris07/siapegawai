<?php

namespace App\Jobs;

use App\Mail\AccountStatusMail;
use App\Models\AccountEmailOutbox;
use App\Models\User;
use App\Services\AccountEmailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendAccountEmail implements ShouldQueue
{
    use Queueable;
    public int $tries = 3;
    public int $timeout = 25;
    public function backoff(): array { return [60, 300]; }
    public function __construct(public int $outboxId) {}

    public function handle(AccountEmailService $service): void
    {
        if (! $service->ready()) return; // retain pending until operator enables real SMTP.
        $row = DB::transaction(function () {
            $item = AccountEmailOutbox::query()->whereKey($this->outboxId)->lockForUpdate()->first();
            if (! $item || ! in_array($item->status, ['pending','failed'], true) || $item->attempts >= 3) return null;
            $item->status = 'processing'; $item->attempts++; $item->last_attempt_at = now(); $item->failure_code = null; $item->save();
            return $item;
        });
        if (! $row) return;
        try {
            // Reject stale/mismatched user/email instead of leaking a status to a new address.
            $user = User::query()->find($row->user_id);
            if (! $user || $user->role !== 'participant' || ! hash_equals(mb_strtolower($user->email), mb_strtolower($row->recipient))) {
                AccountEmailOutbox::query()->whereKey($row->id)->update(['status'=>'failed','failure_code'=>'recipient_changed']);
                return;
            }
            Mail::mailer('smtp')->to($row->recipient)->send(new AccountStatusMail($row->event_type, $user->name));
            AccountEmailOutbox::query()->whereKey($row->id)->update(['status'=>'sent','sent_at'=>now(),'failure_code'=>null]);
        } catch (Throwable $e) {
            // Do not persist the raw transport exception: it can include mail credentials or recipients.
            AccountEmailOutbox::query()->whereKey($row->id)->update(['status'=>'failed','failure_code'=>'smtp_transport']);
            // Retries are bounded by $tries; transport may have accepted before a timeout (at-least-once semantics).
            throw new \RuntimeException('smtp_transport');
        }
    }
}
