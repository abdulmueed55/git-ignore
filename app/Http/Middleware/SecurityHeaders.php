<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser-side protection for every response: a Content Security Policy with a
 * per-request nonce (injected <script> tags and inline event handlers do not run),
 * no framing (clickjacking), no MIME sniffing, a strict referrer policy, HSTS on
 * HTTPS, and only the microphone (for Speak attendance) allowed on this origin.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();

        $response = $next($request);
        $headers = $response->headers;

        if (function_exists('header_remove')) {
            header_remove('X-Powered-By');
        }
        $headers->remove('X-Powered-By');

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), geolocation=(), payment=(), usb=(), interest-cohort=(), microphone=(self)');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        if (! $headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', $this->policy());
        }

        return $response;
    }

    private function policy(): string
    {
        $nonce = Vite::cspNonce();
        $self = ["'self'"];

        // Uploaded media is linked through APP_URL, which may differ from the request host.
        $media = array_values(array_unique(array_filter([$this->origin(config('app.url')), $this->origin(config('filesystems.disks.public.url'))])));

        // `npm run dev` serves scripts and styles from the Vite dev server.
        $dev = [];
        if (Vite::isRunningHot() && ($hot = $this->origin(trim((string) @file_get_contents(public_path('hot')))))) {
            $dev = [$hot, preg_replace('/^http/', 'ws', $hot)];
        }

        $rules = [
            'default-src' => $self,
            // Alpine.js evaluates x-data / @click expressions with Function(), hence 'unsafe-eval'.
            'script-src' => [...$self, "'nonce-{$nonce}'", "'unsafe-eval'", ...$dev],
            'style-src' => [...$self, "'unsafe-inline'", ...$dev],
            'img-src' => [...$self, 'data:', 'blob:', ...$media],
            'media-src' => [...$self, ...$media],
            'font-src' => [...$self, 'data:', ...$dev],
            'connect-src' => [...$self, ...$dev],
            'frame-src' => ["'none'"],
            'object-src' => ["'none'"],
            'base-uri' => $self,
            'form-action' => $self,
            'frame-ancestors' => ["'none'"],
        ];

        return collect($rules)->map(fn ($v, $k) => $k.' '.implode(' ', array_unique($v)))->implode('; ');
    }

    private function origin(?string $url): ?string
    {
        $p = $url ? parse_url($url) : null;
        if (! $p || empty($p['scheme']) || empty($p['host']) || ! in_array($p['scheme'], ['http', 'https'], true)) {
            return null;
        }

        return $p['scheme'].'://'.$p['host'].(isset($p['port']) ? ':'.$p['port'] : '');
    }
}
