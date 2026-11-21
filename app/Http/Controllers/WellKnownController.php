<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Response;

/** robots.txt (bloqueia tudo fora de produção), llms.txt e security.txt (RFC 9116). */
class WellKnownController extends Controller
{
    public function robots(): Response
    {
        if (! app()->environment('production')) {
            $body = "User-agent: *\nDisallow: /\n";
        } else {
            $admin = '/'.trim(config('geofloresta.admin_path'), '/').'/';
            // Content Signals (contentsignals.org): aparecer em pesquisas e respostas de IA (com ligação), sem uso para treinar modelos.
            $body = "# Content Signals: search = indexar e mostrar resultados; ai-input = usar o conteúdo em respostas de IA;\n"
                ."# ai-train = treinar ou afinar modelos de IA. Mais informação: https://contentsignals.org/\n"
                ."User-agent: *\nContent-Signal: search=yes, ai-input=yes, ai-train=no\nAllow: /\nDisallow: {$admin}\nDisallow: /livewire/\nDisallow: /*?preview=\n\n"
                ."# Resumo do site para agentes de IA: ".url('/llms.txt')."\nSitemap: ".url('/sitemap.xml')."\n";
        }

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** llms.txt (llmstxt.org): resumo do site em Markdown, com as páginas e ferramentas principais. */
    public function llms(): Response
    {
        return response(\App\Support\LlmsTxt::build(), 200, ['Content-Type' => 'text/markdown; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    public function security(): Response
    {
        $email = Setting::get('contact_email') ?: config('geofloresta.contact_email');
        $expires = now()->addYear()->startOfDay()->toIso8601ZuluString();
        $body = "Contact: mailto:{$email}\nExpires: {$expires}\nPreferred-Languages: pt, en, fr\nCanonical: ".url('/.well-known/security.txt')."\nPolicy: ".lroute('privacy', [], 'pt')."\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
