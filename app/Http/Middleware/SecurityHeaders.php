<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Add browser security headers to every HTTP response.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if ($request->isSecure() || app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', $this->policy());
        }

        return $response;
    }

    protected function policy(): string
    {
        $vite = $this->viteOrigins();
        $connect = array_values(array_unique([
            "'self'",
            'ws:',
            'wss:',
            'https:',
            'http://127.0.0.1:8080',
            'ws://127.0.0.1:8080',
            'https://api.open-meteo.com',
            'https://geocoding-api.open-meteo.com',
            'https://api.qrserver.com',
            ...$vite,
        ]));
        $scripts = array_values(array_unique([
            "'self'",
            "'unsafe-inline'",
            "'unsafe-eval'",
            ...$vite,
        ]));
        $styles = array_values(array_unique([
            "'self'",
            "'unsafe-inline'",
            'https://fonts.bunny.net',
            ...$vite,
        ]));
        $fonts = array_values(array_unique([
            "'self'",
            'data:',
            'https:',
            'https://fonts.bunny.net',
            ...$vite,
        ]));

        return implode('; ', [
            "default-src 'self'",
            'script-src '.implode(' ', $scripts),
            'style-src '.implode(' ', $styles),
            "img-src 'self' data: blob: https: http:",
            "media-src 'self' blob: https: http:",
            'font-src '.implode(' ', $fonts),
            'connect-src '.implode(' ', $connect),
            "frame-src 'self' https: http: https://www.youtube.com https://www.youtube-nocookie.com",
            "child-src 'self' https: http: https://www.youtube.com https://www.youtube-nocookie.com",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);
    }

    /**
     * Allow the running Vite dev server, including whatever host/port it bound to.
     *
     * @return list<string>
     */
    protected function viteOrigins(): array
    {
        if (app()->isProduction()) {
            return [];
        }

        $origins = [
            'http://127.0.0.1:5173',
            'http://localhost:5173',
            'http://[::1]:5173',
            'ws://127.0.0.1:5173',
            'ws://localhost:5173',
            'ws://[::1]:5173',
            'http://127.0.0.1:5174',
            'http://localhost:5174',
            'http://[::1]:5174',
            'ws://127.0.0.1:5174',
            'ws://localhost:5174',
            'ws://[::1]:5174',
        ];

        $hotFile = public_path('hot');

        if (! is_file($hotFile)) {
            return $origins;
        }

        $hot = trim((string) file_get_contents($hotFile));
        $parts = parse_url($hot);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return $origins;
        }

        $host = $parts['host'];
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $bracketed = str_contains($host, ':') ? '['.$host.']' : $host;
        $wsScheme = $parts['scheme'] === 'https' ? 'wss' : 'ws';

        $aliases = [$bracketed];

        if (in_array($host, ['127.0.0.1', 'localhost', '::1'], true) || $bracketed === '[::1]') {
            $aliases = ['127.0.0.1', 'localhost', '[::1]'];
        }

        foreach ($aliases as $alias) {
            $origins[] = $parts['scheme'].'://'.$alias.$port;
            $origins[] = $wsScheme.'://'.$alias.$port;
        }

        return array_values(array_unique($origins));
    }
}
