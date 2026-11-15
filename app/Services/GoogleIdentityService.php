<?php

namespace App\Services;

use App\Models\AccessGrant;
use App\Models\AuditLog;
use App\Models\ConsentRecord;
use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Minimal provider identity storage; account ownership is NEVER determined by email alone. */
class GoogleIdentityService
{
    public function enabled(): bool
    {
        return config('siapegawai.google_auth_enabled') === true
            && filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'))
            && class_exists(\Laravel\Socialite\Facades\Socialite::class)
            && Schema::hasTable('user_identities');
    }

    public function registrationOpen(): bool
    {
        $version = config('siapegawai.privacy_version');
        return config('siapegawai.registration_enabled') === true
            && config('siapegawai.privacy_approved') === true
            && config('siapegawai.terms_approved') === true
            && is_string($version) && trim($version) !== ''
            && strcasecmp(trim($version), 'DRAFT') !== 0;
    }

    public function verifiedProfile(\Laravel\Socialite\Two\User $profile): array
    {
        $subject = trim((string) $profile->getId());
        $email = mb_strtolower(trim((string) $profile->getEmail()));
        // Socialite's Google provider obtains this claim from Google's authenticated userinfo endpoint.
        // Never treat an unverified address as proof that someone owns an existing local account.
        $verified = $profile->user['email_verified'] ?? null;
        if (! is_string($subject) || $subject === '' || strlen($subject) > 255
            || ! preg_match('/^[A-Za-z0-9_.:-]+$/D', $subject)
            || ! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255
            || ! in_array($verified, [true, 1, 'true', '1'], true)) {
            throw ValidationException::withMessages(['google' => 'Identitas Google tidak dapat diverifikasi. Coba kembali.']);
        }
        return [
            'subject' => $subject,
            'email' => $email,
            'name' => mb_substr(trim((string) $profile->getName()), 0, 120),
        ];
    }

    public function register(array $profile, string $name, string $whatsapp): User
    {
        if (! $this->registrationOpen()) {
            throw ValidationException::withMessages(['google' => 'Pendaftaran belum dibuka oleh pengelola.']);
        }
        return DB::transaction(function () use ($profile, $name, $whatsapp): User {
            // The unique indices enforce the same guards across concurrent callbacks/sessions.
            if (UserIdentity::query()->where('provider', 'google')->where('provider_subject', $profile['subject'])->exists()
                || User::query()->where('email', $profile['email'])->exists()) {
                throw ValidationException::withMessages(['google' => 'Alamat email atau identitas sudah digunakan. Silakan masuk dengan akun yang tersedia.']);
            }
            $number = preg_replace('/\D/', '', $whatsapp);
            $user = new User();
            $user->name = $name;
            $user->email = $profile['email'];
            $user->whatsapp_number = str_starts_with($number, '0') ? '62'.substr($number, 1) : $number;
            // Unshared random password: Google-only users can later use the password-reset broker.
            $user->password = Str::random(64);
            $user->role = 'participant';
            $user->status = 'active';
            $user->activated_at = now();
            $user->activated_by = null;
            $user->save();
            UserIdentity::query()->create([
                'user_id' => $user->id, 'provider' => 'google', 'provider_subject' => $profile['subject'],
            ]);
            ConsentRecord::query()->create([
                'user_id' => $user->id, 'policy_version' => config('siapegawai.privacy_version'),
                'agreed_at' => now(), 'source' => 'google_registration',
            ]);
            AccessGrant::query()->create([
                'user_id' => $user->id, 'grant_type' => 'member',
                'starts_at' => now(), 'status' => 'active', 'granted_by' => null,
            ]);
            AuditLog::query()->create([
                'actor_id' => $user->id, 'action' => 'registration.google_activated',
                'subject_type' => 'user', 'subject_id' => $user->id,
                'before_summary' => null,
                'after_summary' => ['status' => 'active', 'source' => 'google_registration'],
                'created_at' => now(),
            ]);
            app(AccountEmailService::class)->record($user, 'google_registered');
            return $user;
        }, 3);
    }

    public function link(User $actor, array $profile): void
    {
        DB::transaction(function () use ($actor, $profile): void {
            $user = User::query()->whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($user->role === 'participant' && $user->status === 'active', 403);
            if (! hash_equals(mb_strtolower($user->email), $profile['email'])) {
                throw ValidationException::withMessages(['google' => 'Email Google harus sama dengan email akun peserta.']);
            }
            $existing = UserIdentity::query()->where('provider', 'google')
                ->where('provider_subject', $profile['subject'])->first();
            if ($existing || UserIdentity::query()->where('provider', 'google')
                ->where('user_id', $user->id)->exists()) {
                throw ValidationException::withMessages(['google' => 'Identitas Google sudah tertaut. Tidak ada perubahan akun.']);
            }
            UserIdentity::query()->create([
                'user_id' => $user->id, 'provider' => 'google', 'provider_subject' => $profile['subject'],
            ]);
            AuditLog::query()->create([
                'actor_id' => $user->id, 'action' => 'participant.google_linked',
                'subject_type' => 'user', 'subject_id' => $user->id,
                'before_summary' => null, 'after_summary' => ['provider' => 'google'],
                'created_at' => now(),
            ]);
        }, 3);
    }
}
