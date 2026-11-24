<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\ParticipantInvitationOnboardingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class ParticipantInvitationController extends Controller
{
    private function superadmin(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor && $actor->role === 'superadmin' && $actor->status === 'active', 403);
        return $actor;
    }

    public function create(Request $request): View
    {
        $this->superadmin($request);
        return view('admin.participants.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $this->superadmin($request);
        $pilot=app(\App\Services\PilotAccessService::class);
        if ($pilot->enabled()) $request->validate(['whatsapp_number'=>'prohibited']);
        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower(trim($request->input('email')))]);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'whatsapp_number' => ['nullable', 'regex:/^(?:\+?62|0)8\d{7,12}$/'],
        ]);
        $member = DB::transaction(function () use ($data, $actor, $pilot): User {
            if ($pilot->enabled()) $pilot->lockAdmission();
            $number = preg_replace('/\D/', '', $data['whatsapp_number'] ?? '');
            $member = new User();
            $member->name = $data['name'];
            $member->email = $data['email'];
            $member->whatsapp_number = $number === '' ? null
                : (str_starts_with($number, '0') ? '62'.substr($number, 1) : $number);
            // Unusable, undisclosed random credential. Only a broker-verified reset can establish ownership.
            $member->password = Str::random(64);
            $member->role = 'participant';
            $member->status = 'pending';
            $member->save();
            AuditLog::query()->create([
                'actor_id' => $actor->id, 'action' => 'participant.admin_created',
                'subject_type' => 'user', 'subject_id' => $member->id,
                'before_summary' => null,
                'after_summary' => ['role' => 'participant', 'status' => 'pending', 'source' => 'admin_invitation'],
                'created_at' => now(),
            ]);
            if ($pilot->enabled()) $pilot->issue($member,$actor);
            return $member;
        }, 3);
        $sent = $this->sendInvitation($member, $actor);
        return redirect()->route('admin.participants.show', $member)
            ->with($sent ? 'success' : 'warning', $sent
                ? 'Akun pending dibuat. Permintaan email pengaturan password diterima mailer; konfirmasi penerimaan di kotak masuk tetap diperlukan.'
                : 'Akun pending dibuat, tetapi undangan belum terkirim. Konfigurasikan mailer SMTP pengujian (bukan log/array), lalu kirim ulang.');
    }

    public function resend(Request $request, User $user, ParticipantInvitationOnboardingService $onboarding): RedirectResponse
    {
        $actor = $this->superadmin($request);
        abort_unless($onboarding->isAdminInvitation($user) && $user->status === 'pending', 404);
        if ($onboarding->hasOwnerPassword($user)) {
            return back()->with('warning', 'Password sudah dibuat. Peserta perlu login dan menyetujui kebijakan pada halaman aktivasi.');
        }
        $sent = $this->sendInvitation($user, $actor);
        return back()->with($sent ? 'success' : 'warning', $sent
            ? 'Permintaan pengiriman ulang diterima mailer. Pastikan peserta menerima email.'
            : 'Pengiriman ulang belum berhasil atau masih dibatasi. Periksa mailer dan coba lagi sesuai jeda.');
    }

    public function accept(Request $request, ParticipantInvitationOnboardingService $onboarding): RedirectResponse
    {
        $owner = $request->user();
        abort_unless($owner && $owner->role === 'participant', 403);
        $request->validate(['consent' => ['required', 'accepted']]);
        $pilot=app(\App\Services\PilotAccessService::class);
        if ($pilot->enabled()) $request->validate(['binding'=>'required|string','research'=>'nullable|boolean','research_binding'=>'required_if:research,1|nullable|string']);
        $onboarding->accept($owner,(string)$request->input('binding',''),$request->boolean('research'),(string)$request->input('research_binding',''));
        $owner->refresh();
        return redirect()->route('dashboard')->with('success', 'Akun GRATIS aktif. Akses mengikuti hak paket dasar.');
    }

    private function sendInvitation(User $user, User $actor): bool
    {
        // Log/array mailers may expose the reset token in application logs or have no delivery.
        // Do not dispatch real invitations through these drivers outside isolated tests.
        $pilot=app(\App\Services\PilotAccessService::class);
        if ($pilot->enabled() && (!$pilot->canReset($user) || config('pilot.mail_enabled')!==true || !app()->environment('testing'))) return false;
        $mailer = config('mail.default');
        if (! app()->environment('testing') && in_array($mailer, ['log', 'array'], true)) {
            $status = null;
        } else try {
            $status = Password::sendResetLink(['email' => $user->email]);
        } catch (Throwable) {
            $status = null;
        }
        $sent = $status === Password::RESET_LINK_SENT;
        AuditLog::query()->create([
            'actor_id' => $actor->id,
            'action' => $sent ? 'participant.invitation_sent' : 'participant.invitation_send_failed',
            'subject_type' => 'user', 'subject_id' => $user->id,
            'before_summary' => null,
            'after_summary' => ['mailer_accepted' => $sent],
            'created_at' => now(),
        ]);
        return $sent;
    }
}
