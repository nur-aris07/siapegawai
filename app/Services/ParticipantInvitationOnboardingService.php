<?php

namespace App\Services;

use App\Models\AccessGrant;
use App\Models\AuditLog;
use App\Models\ConsentRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Invitation-only onboarding, completely independent from historical web registrations. */
class ParticipantInvitationOnboardingService
{
    public function isAdminInvitation(User $user): bool
    {
        return $user->role === 'participant'
            && ! $user->registrationRequest()->exists()
            && AuditLog::query()->where('subject_type', 'user')->where('subject_id', $user->id)
                ->where('action', 'participant.admin_created')->exists();
    }

    public function hasOwnerPassword(User $user): bool
    {
        return AuditLog::query()->where('subject_type', 'user')->where('subject_id', $user->id)
            ->where('action', 'participant.invitation_password_set')->exists();
    }

    public function legalReady(): bool
    {
        $version = config('siapegawai.privacy_version');
        // The public registration switch and WhatsApp number are unrelated to legal consent for an existing invitation.
        return config('siapegawai.privacy_approved') === true
            && config('siapegawai.terms_approved') === true
            && is_string($version) && trim($version) !== ''
            && strcasecmp(trim($version), 'DRAFT') !== 0;
    }

    /** Returns false on an authenticated replay of an already accepted invitation. */
    public function accept(User $owner): bool
    {
        if (! $this->legalReady()) {
            throw ValidationException::withMessages(['consent' => 'Kebijakan belum disahkan pengelola. Aktivasi belum tersedia.']);
        }
        return DB::transaction(function () use ($owner): bool {
            $user = User::query()->whereKey($owner->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($this->isAdminInvitation($user), 403);
            if ($user->status === 'active' && AuditLog::query()->where('subject_type', 'user')
                ->where('subject_id', $user->id)->where('action', 'participant.invitation_accepted')->exists()) {
                return false;
            }
            abort_unless($user->status === 'pending', 403);
            if (! $this->hasOwnerPassword($user)) {
                throw ValidationException::withMessages(['consent' => 'Atur password melalui tautan undangan sebelum menyetujui kebijakan.']);
            }
            $user->forceFill(['status' => 'active', 'activated_at' => now(), 'activated_by' => null])->save();
            ConsentRecord::query()->create([
                'user_id' => $user->id,
                'policy_version' => config('siapegawai.privacy_version'),
                'agreed_at' => now(), 'source' => 'admin_invitation',
            ]);
            if (! AccessGrant::query()->where('user_id', $user->id)
                ->where('grant_type', 'member')->where('status', 'active')->exists()) {
                AccessGrant::query()->create([
                    'user_id' => $user->id, 'grant_type' => 'member',
                    'starts_at' => now(), 'status' => 'active', 'granted_by' => null,
                ]);
            }
            AuditLog::query()->create([
                'actor_id' => $user->id, 'action' => 'participant.invitation_accepted',
                'subject_type' => 'user', 'subject_id' => $user->id,
                'before_summary' => ['status' => 'pending'],
                'after_summary' => ['status' => 'active', 'source' => 'admin_invitation',
                    'policy_version' => config('siapegawai.privacy_version')],
                'created_at' => now(),
            ]);
            return true;
        }, 3);
    }
}
