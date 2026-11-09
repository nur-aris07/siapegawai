<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ParticipantAccountService
{
    /**
     * Account restrictions are independent of the original registration decision.
     * Never use this operation to approve a pending or rejected registration.
     */
    public function changeStatus(User $participant, User $actor, string $action, string $reason): void
    {
        abort_unless($actor->role === 'superadmin' && $actor->status === 'active', 403);

        DB::transaction(function () use ($participant, $actor, $action, $reason): void {
            $locked = User::query()->whereKey($participant->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($locked->role === 'participant', 404);

            if ($action === 'suspend' && $locked->status === 'active') {
                $target = 'suspended';
            } elseif ($action === 'restore' && $locked->status === 'suspended' && $locked->suspended_at !== null) {
                $target = 'active';
            } else {
                throw ValidationException::withMessages([
                    'reason' => 'Transisi status tidak diizinkan. Muat ulang detail akun dan periksa statusnya.',
                ]);
            }

            $before = ['status' => $locked->status, 'suspended_at' => $locked->suspended_at?->toIso8601String()];
            $locked->status = $target;
            $locked->suspended_at = $target === 'suspended' ? now() : null;
            $locked->save();

            AuditLog::query()->create([
                'actor_id' => $actor->id,
                'action' => $action === 'suspend' ? 'participant.suspend' : 'participant.restore',
                'subject_type' => 'user',
                'subject_id' => $locked->id,
                'before_summary' => $before,
                'after_summary' => ['status' => $target, 'reason' => $reason],
                'created_at' => now(),
            ]);
        }, 3);
    }
}
