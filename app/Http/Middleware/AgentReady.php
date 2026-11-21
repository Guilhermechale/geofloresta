<?php

namespace App\Http\Middleware;

use App\Support\HtmlToMarkdown;
use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ajuda os agentes de IA a ler o site:
 *  - cabeçalho Link (RFC 8288) com o llms.txt, a versão Markdown da página, os termos e a privacidade;
 *  - versão Markdown do conteúdo principal quando o pedido prefere «text/markdown» (negociação de conteúdo).
 * Os navegadores continuam a receber HTML; «Vary: Accept» evita que caches misturem as duas versões.
 */
class AgentReady
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethod('GET') || $response->getStatusCode() !== 200
            || ! str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')) {
            return $response;
        }

        $url = $request->url();
        $links = ['<'.url('/llms.txt').'>; rel="describedby"; type="text/markdown"', '<'.$url.'>; rel="alternate"; type="text/markdown"'];
        try {
            $links[] = '<'.Locales::route('terms').'>; rel="terms-of-service"';
            $links[] = '<'.Locales::route('privacy').'>; rel="privacy-policy"';
        } catch (\Throwable) {
            // rotas legais indisponíveis (por exemplo, em testes de componentes)
        }
        $response->headers->set('Link', implode(', ', $links), false);
        $response->setVary(array_unique(array_merge($response->getVary(), ['Accept'])));

        $accept = (string) $request->header('Accept');
        if (str_contains($accept, 'text/markdown') && $request->prefers(['text/html', 'text/markdown']) === 'text/markdown') {
            $md = HtmlToMarkdown::page((string) $response->getContent(), $url);
            $response->setContent($md);
            $response->headers->set('Content-Type', 'text/markdown; charset=UTF-8');
            $response->headers->remove('Content-Security-Policy');
            $response->headers->set('X-Robots-Tag', 'noindex'); // a versão HTML é a canónica
        }

        return $response;
    }
}
