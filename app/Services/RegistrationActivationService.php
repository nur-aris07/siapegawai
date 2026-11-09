<?php

namespace App\Services;

use App\Models\AccessGrant;
use App\Models\AuditLog;
use App\Models\ManualConfirmation;
use App\Models\RegistrationRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrationActivationService
{
    public function decide(User $subject, User $admin, string $decision, string $note, ?string $externalReference = null): void
    {
        DB::transaction(function () use ($subject, $admin, $decision, $note, $externalReference) {
            $request = RegistrationRequest::query()->where('user_id', $subject->id)->lockForUpdate()->firstOrFail();
            $subject = User::query()->whereKey($subject->id)->lockForUpdate()->firstOrFail();
            if ($request->status !== 'pending' || $subject->status !== 'pending') {
                throw ValidationException::withMessages(['decision' => 'Pendaftaran ini sudah diproses. Muat ulang halaman.']);
            }
            $next = $decision === 'approve' ? 'active' : 'rejected';
            $subject->status = $next;
            if ($decision === 'approve') {
                $subject->activated_at = now();
                $subject->activated_by = $admin->id;
                AccessGrant::query()->create([
                    'user_id' => $subject->id, 'grant_type' => 'member',
                    'starts_at' => now(), 'status' => 'active', 'granted_by' => $admin->id,
                ]);
            }
            $subject->save();
            $request->update(['status' => $next, 'reviewed_by' => $admin->id, 'reviewed_at' => now(), 'admin_note' => $note]);
            ManualConfirmation::query()->create([
                'registration_request_id' => $request->id, 'user_id' => $subject->id,
                'context_type' => 'registration', 'confirmation_type' => 'whatsapp',
                'external_reference' => $externalReference, 'decision' => $decision,
                'note' => $note, 'confirmed_by' => $admin->id, 'confirmed_at' => now(),
            ]);
            AuditLog::query()->create([
                'actor_id' => $admin->id, 'action' => 'registration.'.$decision,
                'subject_type' => 'user', 'subject_id' => $subject->id,
                'before_summary' => ['status' => 'pending'], 'after_summary' => ['status' => $next],
                'created_at' => now(),
            ]);
        }, 3);
    }
}
