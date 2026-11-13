<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Substitui os marcadores dos textos editáveis por conteúdo gerado a partir do que o site
 * realmente usa: {{cookie_table}}, {{sources_table}}, {{analytics_status}}, {{contact_email}}.
 */
class PageBody
{
    public static function render(?string $html): string
    {
        if (! $html) {
            return '';
        }
        $email = e(Setting::get('contact_email') ?: config('geofloresta.contact_email'));

        $replacements = [
            '{{contact_email}}' => $email,
            '{{analytics_status}}' => e(self::analyticsStatus()),
        ];
        if (str_contains($html, '{{cookie_table}}')) {
            $replacements['{{cookie_table}}'] = view('partials.cookie-table', ['items' => Consent::items()])->render();
        }
        if (str_contains($html, '{{sources_table}}')) {
            $replacements['{{sources_table}}'] = view('partials.sources-table', ['sources' => config('sources')])->render();
        }

        return strtr($html, $replacements);
    }

    public static function analyticsStatus(): string
    {
        $locale = app()->getLocale();
        if (! config('geofloresta.ga4_id')) {
            return __('cookies.no_analytics');
        }

        return [
            'pt' => 'Usamos o Google Analytics 4 apenas se aceitar a categoria «Analítica». Sem esse consentimento o script do Google não é carregado. Com consentimento, o Google recebe dados de navegação (páginas vistas, dispositivo, país aproximado), conservados pelo prazo definido na nossa conta do Google Analytics.',
            'fr' => 'Nous utilisons Google Analytics 4 uniquement si vous acceptez la catégorie « Mesure d’audience ». Sans ce consentement, le script de Google n’est pas chargé. Avec consentement, Google reçoit des données de navigation (pages vues, appareil, pays approximatif), conservées selon la durée définie dans notre compte Google Analytics.',
            'en' => 'We use Google Analytics 4 only if you accept the “Analytics” category. Without that consent Google’s script is not loaded. With consent, Google receives browsing data (pages viewed, device, approximate country), kept for the period set in our Google Analytics account.',
        ][$locale] ?? '';
    }
}
