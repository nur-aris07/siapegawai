<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserIdentity;
use App\Services\GoogleIdentityService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    private const TTL = 600;

    /** Only server-owned, allowlisted outcome codes can be surfaced to the guest. */
    private function signinFeedback(string $code): RedirectResponse
    {
        $messages = [
            'cancelled' => ['Login Google dibatalkan', 'Kamu membatalkan proses di Google. Akun Siapegawai tidak berubah.'],
            'expired' => ['Sesi Google telah berakhir', 'Proses Google tidak selesai atau sesi browser kedaluwarsa. Mulai kembali dari tombol Google di bawah.'],
            'provider_error' => ['Koneksi Google gagal', 'Kami belum dapat memverifikasi akun Google. Periksa koneksi atau coba kembali.'],
            'invalid_identity' => ['Identitas Google belum terverifikasi', 'Akun Google belum memberikan identitas atau email terverifikasi yang dapat digunakan. Pilih akun Google lain atau periksa verifikasi email.'],
            'blocked' => ['Akun belum dapat masuk', 'Akun terkait tidak dapat menggunakan Login Google saat ini. Periksa status akun atau hubungi pengelola.'],
            'registration_closed' => ['Pendaftaran Google belum tersedia', 'Pendaftaran akun baru sedang ditutup. Akun yang sudah tertaut tetap dapat masuk jika aktif.'],
            'email_exists' => ['Email sudah terdaftar', 'Email Google ini sudah digunakan oleh akun Siapegawai yang belum ditautkan. Demi keamanan, akun tidak digabung otomatis.'],
            'account_not_linkable' => ['Akun tidak dapat ditautkan otomatis', 'Akun dengan email Google ini belum memenuhi syarat penautan atau menggunakan akses khusus. Gunakan cara masuk yang sudah ada, selesaikan aktivasi bila diperlukan, atau hubungi pengelola.'],
            'signup_conflict' => ['Pendaftaran Google belum berhasil', 'Akun belum dibuat. Email atau identitas Google mungkin sudah digunakan. Coba masuk ke akun yang telah ada.'],
        ];
        $info = $messages[$code] ?? $messages['provider_error'];
        return redirect()->route('login')->with('google_feedback', [
            'code' => $code, 'title' => $info[0], 'message' => $info[1],
        ]);
    }

    private function oauthFailure(?array $flow, string $code): RedirectResponse
    {
        if (($flow['purpose'] ?? null) === 'link' && Auth::check()
            && Auth::id() === ($flow['actor'] ?? null)) {
            $message = match ($code) {
                'cancelled' => 'Penautan Google dibatalkan. Akun tidak diubah.',
                'expired' => 'Sesi penautan Google kedaluwarsa. Mulai lagi dari profil.',
                default => 'Penautan Google belum berhasil. Coba kembali dari profil.',
            };
            return redirect()->route('member.profile.edit')
                ->with('warning', $message)->with('google_link_feedback', $message);
        }
        return $this->signinFeedback($code);
    }

    private function available(GoogleIdentityService $identities): void
    {
        abort_unless($identities->enabled(), 503, 'Login Google belum dikonfigurasi.');
    }

    public function redirect(Request $request, GoogleIdentityService $identities): mixed
    {
        $this->available($identities);
        abort_unless(! Auth::check(), 403);
        $request->session()->forget(['siapegawai_google_pending', 'siapegawai_google_flow']);
        $request->session()->put('siapegawai_google_flow', [
            'purpose' => 'signin', 'created' => now()->timestamp,
        ]);
        // Stateful OAuth only: Socialite manages and validates the per-session Google state.
        return Socialite::driver('google')->scopes(['openid', 'profile', 'email'])->redirect();
    }

    public function link(Request $request, GoogleIdentityService $identities): mixed
    {
        $this->available($identities);
        $actor = $request->user();
        abort_unless($actor && $actor->role === 'participant' && $actor->status === 'active', 403);
        $data = $request->validate(['password' => ['required', 'string']]);
        if (! Hash::check($data['password'], $actor->password)) {
            throw ValidationException::withMessages(['password' => 'Kata sandi akun tidak sesuai.']);
        }
        if (UserIdentity::query()->where('user_id', $actor->id)->where('provider', 'google')->exists()) {
            throw ValidationException::withMessages(['password' => 'Akun Google sudah tertaut.']);
        }
        $request->session()->forget(['siapegawai_google_pending', 'siapegawai_google_flow']);
        $request->session()->put('siapegawai_google_flow', [
            'purpose' => 'link', 'actor' => $actor->id, 'created' => now()->timestamp,
        ]);
        return Socialite::driver('google')->scopes(['openid', 'profile', 'email'])->redirect();
    }

    public function callback(Request $request, GoogleIdentityService $identities): RedirectResponse
    {
        $this->available($identities);
        $flow = $request->session()->pull('siapegawai_google_flow');
        $request->session()->forget('siapegawai_google_pending');
        if (! is_array($flow) || ! in_array($flow['purpose'] ?? null, ['signin', 'link'], true)
            || ! is_int($flow['created'] ?? null) || now()->timestamp - $flow['created'] > self::TTL
            || $flow['created'] > now()->timestamp + 30) {
            return $this->oauthFailure(is_array($flow) ? $flow : null, 'expired');
        }
        if ($request->filled('error')) {
            return $this->oauthFailure($flow, $request->input('error') === 'access_denied' ? 'cancelled' : 'provider_error');
        }
        if (($flow['purpose'] === 'link' && (! Auth::check() || Auth::id() !== ($flow['actor'] ?? null)))
            || ($flow['purpose'] === 'signin' && Auth::check())) {
            abort(403);
        }
        try {
            // Socialite validates the OAuth state, exchanges the code and calls Google userinfo.
            $google = Socialite::driver('google')->user();
            $profile = $identities->verifiedProfile($google);
        } catch (ValidationException) {
            return $this->oauthFailure($flow, 'invalid_identity');
        } catch (Throwable) {
            return $this->oauthFailure($flow, 'provider_error');
        }
        if ($flow['purpose'] === 'link') {
            try {
                $identities->link($request->user(), $profile);
            } catch (QueryException|ValidationException) {
                return redirect()->route('member.profile.edit')
                    ->with('warning', 'Google belum dapat dihubungkan. Pastikan email sama dengan email Siapegawai dan belum tertaut ke akun lain.')
                    ->with('google_link_feedback', 'Google belum dapat dihubungkan. Pastikan email sama dengan email Siapegawai dan belum tertaut ke akun lain.');
            }
            return redirect()->route('member.profile.edit')->with('success', 'Akun Google berhasil dihubungkan.')
                ->with('google_link_feedback', 'Berhasil! Login Google kini tertaut ke akun Siapegawai ini.');
        }
        $linked = UserIdentity::query()->where('provider', 'google')
            ->where('provider_subject', $profile['subject'])->first();
        if ($linked) {
            $user = $linked->user;
            if (! $user || $user->role !== 'participant' || $user->status !== 'active') {
                return $this->signinFeedback('blocked');
            }
            Auth::login($user);
            $request->session()->regenerate();
            return redirect()->route('dashboard')->with('success', 'Berhasil masuk dengan Google.')->setStatusCode(303);
        }
        if (! $identities->registrationOpen()) {
            return $this->signinFeedback('registration_closed');
        }
        // Never associate a Google identity to an existing local account based on email alone.
        $existing = User::query()->where('email', $profile['email'])->first();
        if ($existing) {
            if ($existing->role !== 'participant' || $existing->status !== 'active'
                || UserIdentity::query()->where('user_id', $existing->id)->where('provider', 'google')->exists()) {
                return $this->signinFeedback('account_not_linkable');
            }
            return $this->signinFeedback('email_exists');
        }
        $request->session()->put('siapegawai_google_pending', [
            ...$profile, 'created' => now()->timestamp, 'nonce' => bin2hex(random_bytes(16)),
        ]);
        return redirect()->route('google.onboarding')->setStatusCode(303);
    }

    private function pending(Request $request): ?array
    {
        $profile = $request->session()->get('siapegawai_google_pending');
        if (! is_array($profile) || ! is_int($profile['created'] ?? null)
            || now()->timestamp - $profile['created'] > self::TTL
            || $profile['created'] > now()->timestamp + 30
            || ! is_string($profile['nonce'] ?? null) || strlen($profile['nonce']) !== 32
            || ! is_string($profile['subject'] ?? null) || ! is_string($profile['email'] ?? null)) {
            $request->session()->forget('siapegawai_google_pending');
            return null;
        }
        return $profile;
    }

    public function onboarding(Request $request, GoogleIdentityService $identities): View|RedirectResponse
    {
        $this->available($identities);
        abort_unless(! Auth::check(), 403);
        if (! $identities->registrationOpen()) {
            $request->session()->forget('siapegawai_google_pending');
            return $this->signinFeedback('registration_closed');
        }
        $pending = $this->pending($request);
        if ($pending === null) return $this->signinFeedback('expired');
        return view('auth.google-onboarding', ['profile' => $pending]);
    }

    public function store(Request $request, GoogleIdentityService $identities): RedirectResponse
    {
        $this->available($identities);
        abort_unless(! Auth::check(), 403);
        if (! $identities->registrationOpen()) {
            $request->session()->forget('siapegawai_google_pending');
            return $this->signinFeedback('registration_closed');
        }
        $pending = $this->pending($request);
        if ($pending === null) return $this->signinFeedback('expired');
        $input = $request->validate([
            'nonce' => ['required', 'string', 'size:32'],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'whatsapp_number' => ['required', 'regex:/^(?:\+?62|0)8\d{7,12}$/'],
            'consent' => ['required', 'accepted'],
        ]);
        abort_unless(hash_equals($pending['nonce'], $input['nonce']), 403);
        try {
            $user = $identities->register($pending, trim($input['name']), $input['whatsapp_number']);
        } catch (QueryException|ValidationException) {
            $request->session()->forget('siapegawai_google_pending');
            return $this->signinFeedback('signup_conflict');
        }
        $request->session()->forget('siapegawai_google_pending');
        Auth::login($user);
        $request->session()->regenerate();
        return redirect()->route('dashboard')->with('success', 'Akun Google berhasil dibuat. Selamat datang! Paket dasar Pejuang aktif.')->setStatusCode(303);
    }
}
