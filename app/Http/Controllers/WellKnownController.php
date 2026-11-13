<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Response;

/** robots.txt (bloqueia tudo fora de produção) e security.txt (RFC 9116). */
class WellKnownController extends Controller
{
    public function robots(): Response
    {
        if (! app()->environment('production')) {
            $body = "User-agent: *\nDisallow: /\n";
        } else {
            $admin = '/'.trim(config('geofloresta.admin_path'), '/').'/';
            $body = "User-agent: *\nAllow: /\nDisallow: {$admin}\nDisallow: /livewire/\nDisallow: /*?preview=\n\nSitemap: ".url('/sitemap.xml')."\n";
        }

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function security(): Response
    {
        $email = Setting::get('contact_email') ?: config('geofloresta.contact_email');
        $expires = now()->addYear()->startOfDay()->toIso8601ZuluString();
        $body = "Contact: mailto:{$email}\nExpires: {$expires}\nPreferred-Languages: pt, en, fr\nCanonical: ".url('/.well-known/security.txt')."\nPolicy: ".lroute('privacy', [], 'pt')."\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
