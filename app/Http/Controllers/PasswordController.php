<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Events\PasswordReset;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRules;

class PasswordController extends Controller
{
    public function requestForm(): View { return view('auth.forgot-password'); }
    public function sendLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        $pilot=app(\App\Services\PilotAccessService::class);
        if ($pilot->enabled()) {
            $u=User::query()->where('email',mb_strtolower(trim($request->input('email'))))->first();
            if (!$u || !$pilot->canReset($u) || config('pilot.mail_enabled')!==true || !app()->environment('testing')) return back()->with('success','Jika email terdaftar, instruksi pemulihan akan dikirim.');
        }
        Password::sendResetLink(['email' => mb_strtolower(trim($request->input('email')))]);
        return back()->with('success', 'Jika email terdaftar, instruksi pemulihan akan dikirim.');
    }
    public function resetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email', '')]);
    }
    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'], 'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRules::min(12)->letters()->numbers()],
        ]);
        $status = Password::reset($request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                DB::transaction(function () use ($user, $password): void {
                    $account = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
                    $pilot=app(\App\Services\PilotAccessService::class);
                    if ($pilot->enabled()) abort_unless($pilot->canReset($account),403);
                    $isAdmin = $account->isAdmin();
                    $before = (int) $account->admin_security_version;
                    $changes = [
                        'password' => Hash::make($password),
                        'remember_token' => Str::random(60),
                    ];
                    if ($isAdmin) $changes['admin_security_version'] = $before + 1;
                    $account->forceFill($changes)->save();
                    // Password ownership is proved by Laravel's single-use broker token.
                    // Do NOT equate a pending legacy web-registration with an admin invitation.
                    $invitation = app(\App\Services\ParticipantInvitationOnboardingService::class);
                    if ($account->role === 'participant' && $account->status === 'pending'
                        && $invitation->isAdminInvitation($account)
                        && ! $invitation->hasOwnerPassword($account)) {
                        AuditLog::query()->create([
                            'actor_id' => $account->id,
                            'action' => 'participant.invitation_password_set',
                            'subject_type' => 'user', 'subject_id' => $account->id,
                            'before_summary' => null,
                            'after_summary' => ['proof' => 'password_broker_reset'],
                            'created_at' => now(),
                        ]);
                    }
                    if ($isAdmin) {
                        AuditLog::query()->create([
                            'actor_id' => null,
                            'action' => 'admin.password_reset',
                            'subject_type' => 'user',
                            'subject_id' => $account->id,
                            'before_summary' => ['security_version' => $before],
                            'after_summary' => ['security_version' => $before + 1, 'other_sessions_revoked' => true],
                            'created_at' => now(),
                        ]);
                    }
                    event(new PasswordReset($account));
                }, 3);
            });
        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('success', 'Password diperbarui. Silakan login.')
            : back()->withErrors(['email' => 'Link tidak valid atau kedaluwarsa.']);
    }
}
