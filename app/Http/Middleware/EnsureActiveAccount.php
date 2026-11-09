<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) return redirect()->route('login');

        // Do not rely on an in-memory authenticated user after account suspension,
        // restoration, or role changes. Recheck the database on protected routes.
        $current = $user->fresh();
        abort_unless($current, 403, 'Akun tidak tersedia.');
        $user->setRawAttributes($current->getAttributes(), true);
        $user->setRelations([]);

        if ($user->status !== 'active') {
            // The activation screen is for pending registration, not for suspended/rejected accounts.
            if ($user->role === 'participant' && $user->status === 'pending' && $request->routeIs('dashboard')) {
                return redirect()->route('registration.status');
            }
            abort(403, 'Akun belum aktif.');
        }
        return $next($request);
    }
}
