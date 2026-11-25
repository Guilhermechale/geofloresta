<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Detecção de mensagens enganosas ou de burla no formulário de contacto, sem serviços externos.
 * Soma pontos por sinais típicos; a partir de THRESHOLD a mensagem vai para o spam (fica guardada, com o motivo).
 * Conservador: um só sinal fraco não chega para mandar um cliente verdadeiro para o spam.
 */
class SpamDetector
{
    public const THRESHOLD = 5;

    /** [pontos, rótulo, palavras] — comparadas em minúsculas e sem acentos. */
    protected const RULES = [
        [4, 'oferta de domínio ou motores de busca', ['domain renewal', 'renew your domain', 'domain registration', 'renovacao do dominio', 'renovar o dominio', 'registo de dominio', 'registro de dominio', 'search engine registration', 'search engine submission', 'motores de busca', 'moteurs de recherche', 'domain listing', 'web listing', 'directory listing']],
        [3, 'SEO ou marketing não pedido', [' seo ', ' seo,', ' seo.', 'backlink', 'first page of google', 'top of google', 'primeira pagina do google', 'premiere page de google', 'google ranking', 'guest post', 'increase your traffic', 'aumentar o trafego', 'more leads', 'website redesign', 'redesign your website', 'social media marketing', 'unsubscribe', 'opt out', 'cancelar subscricao']],
        [5, 'dinheiro fácil ou burla financeira', ['bitcoin', 'crypto', 'cripto', 'forex', 'investment opportunity', 'oportunidade de investimento', 'lottery', 'lotaria', 'loterie', 'inheritance', 'heranca', 'beneficiary', 'beneficiario', 'western union', 'moneygram', 'gift card', 'cartao presente', 'next of kin', 'million dollars', 'milhoes de dolares']],
        [3, 'pedido de dados ou pagamento urgente', ['verify your account', 'verifique a sua conta', 'account suspended', 'conta suspensa', 'compte suspendu', 'your password', 'sua palavra-passe', 'sua senha', 'login details', 'dados de acesso', 'payment overdue', 'pagamento em atraso', 'final notice', 'ultimo aviso', 'invoice attached', 'fatura em anexo', 'wire transfer', 'transferencia bancaria']],
        [1, 'linguagem de pressão', ['click here', 'clique aqui', 'cliquez ici', 'act now', 'limited time', 'tempo limitado', 'urgent', 'urgente', '100% free', 'risk free', 'sem risco']],
    ];

    /** @return array{score:int, reasons:array<int,string>} */
    public static function check(string $name, string $email, string $message, ?string $organisation = null): array
    {
        $score = 0;
        $reasons = [];
        $text = ' '.Str::lower(Str::ascii($name.' '.$organisation.' '.$message)).' ';

        // Domínio que imita o nosso (contém «geofloresta» mas não é geofloresta.com): sinal forte de burla
        $domain = Str::lower(Str::after($email, '@'));
        if (str_contains($domain, 'geofloresta') && $domain !== 'geofloresta.com' && ! str_ends_with($domain, '.geofloresta.com')) {
            $score += 10;
            $reasons[] = 'domínio que imita o nosso ('.$domain.')';
        }

        // Cada grupo conta até dois sinais diferentes (várias frases típicas juntas pesam mais)
        foreach (self::RULES as [$points, $label, $words]) {
            $hits = count(array_filter($words, fn ($w) => str_contains($text, $w)));
            if ($hits) {
                $score += $points * min(2, $hits);
                $reasons[] = $label;
            }
        }

        // Muitos links
        $links = preg_match_all('#https?://|www\.#i', $message);
        if ($links >= 3) {
            $score += 3;
            $reasons[] = $links.' ligações na mensagem';
        } elseif ($links >= 1) {
            $score += 1;
        }

        // Nome com ligação ou só com símbolos
        if (preg_match('#https?://|www\.|\.com\b#i', $name)) {
            $score += 5;
            $reasons[] = 'ligação no nome';
        }

        // Texto sobretudo em alfabetos não latinos (o site é em português, francês e inglês)
        $letters = preg_match_all('/\p{L}/u', $message);
        $nonLatin = preg_match_all('/[\p{Cyrillic}\p{Han}\p{Arabic}\p{Hangul}\p{Thai}]/u', $message);
        if ($letters > 20 && $nonLatin / $letters > 0.5) {
            $score += 4;
            $reasons[] = 'texto noutro alfabeto';
        }

        return ['score' => $score, 'reasons' => array_values(array_unique($reasons))];
    }

    public static function isSpam(array $result): bool
    {
        return $result['score'] >= self::THRESHOLD;
    }
}
