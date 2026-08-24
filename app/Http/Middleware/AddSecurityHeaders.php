<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));
        app()->instance('csp-nonce', $nonce);

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // En local, le serveur de dev Vite sert les scripts/styles et le HMR
        // depuis une origine distincte (localhost:5173) : sans ces exceptions,
        // la CSP bloque tout le JS de l'app et la page reste blanche.
        $viteDevOrigins = app()->environment('local')
            ? ['http://localhost:5173', 'http://127.0.0.1:5173', 'ws://localhost:5173', 'ws://127.0.0.1:5173']
            : [];

        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            implode(' ', array_merge(["script-src 'self' 'nonce-{$nonce}'"], $viteDevOrigins)),
            implode(' ', array_merge(["style-src 'self' 'unsafe-inline' https://fonts.bunny.net"], $viteDevOrigins)),
            "font-src 'self' https://fonts.bunny.net data:",
            "img-src 'self' data: blob:",
            implode(' ', array_merge(["connect-src 'self'"], $viteDevOrigins)),
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]));

        return $response;
    }
}
