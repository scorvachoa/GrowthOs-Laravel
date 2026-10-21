<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Prevent clickjacking
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Enable XSS protection in older browsers
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Referrer policy - send origin only on cross-origin requests
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions policy - disable unused browser features
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        // Content Security Policy - prevent XSS and data injection
        [$devHttp, $devWs] = $this->viteDevSources();
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'{$devHttp}; style-src 'self' 'unsafe-inline' https://fonts.bunny.net{$devHttp}; img-src 'self' data: https:; media-src 'self' data: https:; font-src 'self' data: https://fonts.bunny.net; connect-src 'self' https://fonts.bunny.net{$devHttp}{$devWs}; frame-src https://www.youtube.com https://www.tiktok.com;");

        // Prevent cross-domain policy loading
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        // HSTS - only in production with HTTPS
        if (config('app.env') === 'production' && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    /**
     * Allow the Vite dev server origins when running locally with
     * `npm run dev` (public/hot file present).
     *
     * Returns [http sources, websocket sources]. Chromium does not accept
     * IPv6 literals in CSP, so bracketed hosts are skipped (vite.config.js
     * pins the dev server to 127.0.0.1 to avoid that path).
     *
     * @return array{0: string, 1: string}
     */
    private function viteDevSources(): array
    {
        if (config('app.env') !== 'local') {
            return ['', ''];
        }

        $hot = public_path('hot');
        if (! is_file($hot)) {
            return ['', ''];
        }

        $url = trim((string) file_get_contents($hot));
        if ($url === '' || ! str_starts_with($url, 'http')) {
            return ['', ''];
        }

        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? 'http';
        $host = $parts['host'] ?? 'localhost';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        $http = '';
        $ws = '';

        if (! str_contains($host, ':')) {
            $http = $scheme.'://'.$host.$port;
            $ws = ($scheme === 'https' ? 'wss' : 'ws').'://'.$host.$port;

            if ($host !== 'localhost') {
                $http .= ' '.$scheme.'://localhost'.$port;
                $ws .= ' '.($scheme === 'https' ? 'wss' : 'ws').'://localhost'.$port;
            }
        }

        return [$http === '' ? '' : ' '.$http, $ws === '' ? '' : ' '.$ws];
    }
}
