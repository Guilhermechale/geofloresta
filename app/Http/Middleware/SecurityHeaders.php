<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeçalhos de segurança. CSP estrita com nonce no site público;
 * o painel Filament (Livewire/Alpine) recebe uma CSP compatível com 'unsafe-eval'.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();
        $response = $next($request);

        $h = $response->headers;
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('X-Frame-Options', 'SAMEORIGIN');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
        $h->set('Cross-Origin-Opener-Policy', 'same-origin');
        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $isAdmin = $request->is(config('geofloresta.admin_path'), config('geofloresta.admin_path').'/*', 'livewire*', 'filament*');
        $h->set('Content-Security-Policy', $isAdmin ? $this->adminCsp() : $this->siteCsp($nonce));

        return $response;
    }

    protected function siteCsp(string $nonce): string
    {
        $ga = config('geofloresta.ga4_id') ? ' https://www.googletagmanager.com' : '';
        $gaConnect = config('geofloresta.ga4_id') ? ' https://*.google-analytics.com https://*.analytics.google.com https://www.googletagmanager.com' : '';
        $tiles = 'https://tiles.openfreemap.org https://gibs.earthdata.nasa.gov https://elevation-tiles-prod.s3.amazonaws.com';

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'{$ga}",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob: {$tiles}".($ga ? ' https://*.google-analytics.com https://www.googletagmanager.com' : ''),
            "font-src 'self'",
            "connect-src 'self' {$tiles}{$gaConnect}",
            "worker-src 'self' blob:",
            "child-src blob:",
            "frame-src https://*.projects.earthengine.app https://*.earthengine.app https://www.youtube-nocookie.com", // mapas interactivos dos tutoriais
            "frame-ancestors 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            'upgrade-insecure-requests',
        ]);
    }

    protected function adminCsp(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "frame-ancestors 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);
    }
}
