<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminSessionVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user();
        abort_unless($admin && $admin->isAdmin() && $admin->status === 'active', 403);

        // EnsureActiveAccount is responsible for refreshing the authenticated model.
        $expected = (int) $admin->admin_security_version;
        $key = 'siapegawai_admin_security_version';
        $hasVersion = $request->session()->has($key);
        $actual = $request->session()->get($key);

        // Legacy admin sessions issued before this patch have no marker.
        // They may continue only until the FIRST password change/reset (version 0).
        if (($hasVersion && (! is_int($actual) || $actual !== $expected))
            || (! $hasVersion && $expected !== 0)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->with('error', 'Sesi administrator berakhir setelah perubahan keamanan. Silakan masuk kembali.');
        }

        return $next($request);
    }
}
