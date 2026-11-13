<?php

use App\Support\Locales;

if (! function_exists('lroute')) {
    /** URL de uma rota no idioma actual (ou no indicado). */
    function lroute(string $name, array $params = [], ?string $locale = null, bool $absolute = true): string
    {
        return Locales::route($name, $params, $locale, $absolute);
    }
}

if (! function_exists('nav_active')) {
    function nav_active(string $name): bool
    {
        return Locales::isActive($name);
    }
}

if (! function_exists('format_date')) {
    /** Data formatada no idioma actual (ex.: "13 de novembro de 2026"). */
    function format_date($date, string $pattern = 'long'): string
    {
        if (! $date) {
            return '';
        }
        $date = $date instanceof DateTimeInterface ? $date : new DateTimeImmutable((string) $date);
        $styles = ['long' => IntlDateFormatter::LONG, 'medium' => IntlDateFormatter::MEDIUM, 'short' => IntlDateFormatter::SHORT];
        $fmt = new IntlDateFormatter(app()->getLocale(), $styles[$pattern] ?? IntlDateFormatter::LONG, IntlDateFormatter::NONE, 'Africa/Maputo');

        return (string) $fmt->format($date);
    }
}

if (! function_exists('format_datetime')) {
    function format_datetime($date): string
    {
        if (! $date) {
            return '';
        }
        $date = $date instanceof DateTimeInterface ? $date : new DateTimeImmutable((string) $date);
        $fmt = new IntlDateFormatter(app()->getLocale(), IntlDateFormatter::LONG, IntlDateFormatter::SHORT, 'Africa/Maputo');

        return $fmt->format($date).' (CAT)';
    }
}

if (! function_exists('format_number')) {
    function format_number(int|float $n, int $decimals = 0): string
    {
        $fmt = new NumberFormatter(app()->getLocale(), NumberFormatter::DECIMAL);
        $fmt->setAttribute(NumberFormatter::FRACTION_DIGITS, $decimals);

        return (string) $fmt->format($n);
    }
}

if (! function_exists('format_bytes')) {
    function format_bytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = $bytes > 0 ? min((int) floor(log($bytes, 1024)), 3) : 0;

        return format_number($bytes / (1024 ** $i), $i ? 1 : 0).' '.$units[$i];
    }
}
