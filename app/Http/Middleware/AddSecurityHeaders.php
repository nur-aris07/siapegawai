<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        if ($request->user() || $request->is('latihan-gratis/hasil', 'pendaftaran/*', 'aktivasi', 'reset-password/*')) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }
        if (app()->isProduction()) {
            // Report-only until all production routes and assets pass CSP QC.
            $response->headers->set('Content-Security-Policy-Report-Only',
                "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self' data:; connect-src 'self'; object-src 'none'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
        }
        return $response;
    }
}
