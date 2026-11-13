<?php

namespace App\Support;

use App\Models\Study;

/** "Como citar" gerado a partir dos metadados da pesquisa (ABNT NBR 6023, APA 7, Vancouver). */
class Citation
{
    /** @return array<string, string> estilo => HTML */
    public static function all(Study $s): array
    {
        $title = e(rtrim($s->getTranslation('title', 'pt'), '.'));
        $year = $s->year ?: $s->published_at?->year ?: now()->year;
        $url = $s->url('pt');
        $link = '<a href="'.e($url).'">'.e($url).'</a>';
        $venue = e($s->venue ?: 'GEOFLORESTA');
        $doi = $s->doi ? ' https://doi.org/'.e($s->doi) : '';
        $authors = $s->authorsList();
        $months = ['jan.', 'fev.', 'mar.', 'abr.', 'maio', 'jun.', 'jul.', 'ago.', 'set.', 'out.', 'nov.', 'dez.'];
        $today = now();

        $abnt = implode('; ', array_map([self::class, 'abntName'], $authors));
        $apa = self::joinApa(array_map([self::class, 'apaName'], $authors));
        $van = implode(', ', array_map([self::class, 'vancouverName'], $authors));

        // Título que termina em "?" ou "!" não leva ponto final a seguir
        $fix = fn (string $h) => preg_replace('/([?!])((?:<\/i>)?)\./u', '$1$2', $h);

        return array_map($fix, [
            'ABNT' => e($abnt).'. '.$title.'. <strong>'.$venue.'</strong>, '.$year.'. Disponível em: '.$link.'. Acesso em: '.$today->day.' '.$months[$today->month - 1].' '.$today->year.'.',
            'APA' => e($apa).' ('.$year.'). <i>'.$title.'</i>. '.$venue.'.'.($doi ?: ' '.$link),
            'Vancouver' => e($van).'. '.$title.'. '.$venue.' [Internet]. '.$year.' [citado '.$today->format('Y').' '.$months[$today->month - 1].' '.$today->day.']. Disponível em: '.$link,
        ]);
    }

    protected static function parts(string $name): array
    {
        $p = preg_split('/\s+/u', trim($name));

        return count($p) > 1 ? [array_pop($p), $p] : [$p[0], []];
    }

    protected static function isOrg(string $name): bool
    {
        return ! str_contains(trim($name), ' ') || mb_strtoupper($name) === $name;
    }

    public static function abntName(string $name): string
    {
        if (self::isOrg($name)) {
            return mb_strtoupper($name);
        }
        [$last, $first] = self::parts($name);

        return mb_strtoupper($last).', '.implode(' ', $first);
    }

    public static function apaName(string $name): string
    {
        if (self::isOrg($name)) {
            return $name;
        }
        [$last, $first] = self::parts($name);

        return $last.', '.implode(' ', array_map(fn ($n) => mb_substr($n, 0, 1).'.', $first));
    }

    public static function vancouverName(string $name): string
    {
        if (self::isOrg($name)) {
            return $name;
        }
        [$last, $first] = self::parts($name);

        return $last.' '.implode('', array_map(fn ($n) => mb_substr($n, 0, 1), $first));
    }

    protected static function joinApa(array $names): string
    {
        if (count($names) < 2) {
            return $names[0] ?? 'GEOFLORESTA';
        }
        $last = array_pop($names);

        return implode(', ', $names).', & '.$last;
    }
}
