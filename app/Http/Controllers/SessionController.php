<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use App\Services\AdminSecurityService;

class SessionController extends Controller
{
    public function create(): View { return view('auth.login'); }

    public function store(Request $request, AdminSecurityService $security): RedirectResponse
    {
        $input = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $email = mb_strtolower(trim($input['email']));
        $key = 'login:'.hash('sha256', $email.'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Terlalu banyak percobaan. Coba lagi beberapa saat.']);
        }
        if (! Auth::attempt(['email' => $email, 'password' => $input['password']])) {
            RateLimiter::hit($key, 60);
            $security->recordLoginFailure($email, $request);
            throw ValidationException::withMessages(['email' => 'Email atau kata sandi tidak sesuai.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        $user = $request->user();
        if ($user->isAdmin() && $user->status !== 'active') {
            $security->recordLoginBlocked($user, $request);
        }
        if ($user->status === 'suspended') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            abort(403, 'Akun ditangguhkan.');
        }
        if ($user->role === 'participant' && $user->status !== 'active') return redirect()->route('registration.status');
        if ($user->status !== 'active') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            abort(403, 'Akun dinonaktifkan.');
        }
        if ($user->isAdmin()) {
            $request->session()->put('siapegawai_admin_security_version', (int) $user->admin_security_version);
            $security->recordLoginSuccess($user, $request);
        } else {
            $request->session()->forget('siapegawai_admin_security_version');
        }
        return redirect()->intended($user->role === 'participant' ? route('dashboard') : route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('home');
    }
}
