<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminSecurityService
{
    /**
     * Store a keyed digest of the source address, never a password or a raw IP.
     * Authentication failure is audited only when the email belongs to an admin.
     */
    public function recordLoginFailure(string $email, Request $request): void
    {
        $admin = User::query()->where('email', $email)
            ->whereIn('role', ['operational_admin', 'superadmin'])->first();
        if ($admin) {
            $this->record($admin, 'admin.login_failed', $request, false);
        }
    }

    public function recordLoginSuccess(User $admin, Request $request): void
    {
        $this->record($admin, 'admin.login_success', $request, true);
    }

    public function recordLoginBlocked(User $admin, Request $request): void
    {
        $this->record($admin, 'admin.login_blocked', $request, false);
    }

    private function record(User $admin, string $action, Request $request, bool $authenticated): void
    {
        AuditLog::query()->create([
            'actor_id' => $authenticated ? $admin->id : null,
            'action' => $action,
            'subject_type' => 'user',
            'subject_id' => $admin->id,
            'before_summary' => null,
            'after_summary' => [
                'source_hash' => substr(hash_hmac('sha256', (string) ($request->ip() ?? 'unknown'), (string) config('app.key')), 0, 24),
                'result' => $action,
            ],
            'created_at' => now(),
        ]);
    }

    /** Password change and security-version increment must be atomic. */
    public function changeOwnPassword(User $actor, string $currentPassword, string $newPassword): void
    {
        DB::transaction(function () use ($actor, $currentPassword, $newPassword): void {
            $admin = User::query()->whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($admin->isAdmin() && $admin->status === 'active', 403);
            if (! Hash::check($currentPassword, $admin->password)) {
                throw ValidationException::withMessages(['current_password' => 'Kata sandi saat ini tidak sesuai.']);
            }
            if (Hash::check($newPassword, $admin->password)) {
                throw ValidationException::withMessages(['password' => 'Kata sandi baru harus berbeda dari yang lama.']);
            }

            $before = (int) $admin->admin_security_version;
            $admin->forceFill([
                'password' => Hash::make($newPassword),
                'remember_token' => Str::random(60),
                'admin_security_version' => $before + 1,
            ])->save();

            AuditLog::query()->create([
                'actor_id' => $admin->id,
                'action' => 'admin.password_changed',
                'subject_type' => 'user',
                'subject_id' => $admin->id,
                'before_summary' => ['security_version' => $before],
                'after_summary' => ['security_version' => $before + 1, 'other_sessions_revoked' => true],
                'created_at' => now(),
            ]);
        }, 3);
    }
}
