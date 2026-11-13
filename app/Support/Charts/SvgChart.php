<?php

namespace App\Support\Charts;

/**
 * Gráficos SVG simples, sem JavaScript, com as cores definidas no CSS (classes .c-s1 … e .c-c1 …),
 * por isso seguem automaticamente o tema claro/escuro.
 * Cada marca tem data-tip (tooltip ao passar o rato/tocar) e <title> (leitores de ecrã).
 * Desenha-se em duas larguras: 'wide' (≥ 600 px) e 'compact' (telemóvel), com letra legível nas duas.
 */
class SvgChart
{
    public const SIZES = [
        'wide' => ['w' => 760, 'h' => 400, 'font' => 13, 'ticks' => 5, 'xEvery' => 3],
        'compact' => ['w' => 360, 'h' => 300, 'font' => 12, 'ticks' => 4, 'xEvery' => 6],
    ];

    public static function num(float $v, int $dec = 0): string
    {
        return number_format($v, $dec, ',', '.');
    }

    public static function short(float $v): string
    {
        if ($v >= 1_000_000) {
            return self::num($v / 1_000_000, $v % 1_000_000 ? 1 : 0).' M';
        }
        if ($v >= 1000) {
            return self::num($v / 1000).' mil';
        }

        return self::num($v);
    }

    protected static function niceMax(float $max, int $ticks): array
    {
        $raw = $max / $ticks;
        $mag = 10 ** floor(log10(max($raw, 1)));
        foreach ([1, 2, 2.5, 5, 10] as $m) {
            if ($m * $mag >= $raw) {
                $step = $m * $mag;
                break;
            }
        }

        return [$step * $ticks, $step];
    }

    protected static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /**
     * Linhas ao longo do tempo.
     * @param list<int> $x anos
     * @param list<array{label: string, values: list<float>, class: string, dash?: string}> $series
     */
    public static function lines(array $x, array $series, string $unit, string $size): string
    {
        $S = self::SIZES[$size];
        $f = $S['font'];
        $padL = $f * 4.6;
        $padR = $size === 'wide' ? 150 : 12;
        $padT = $size === 'wide' ? 16 : 16 + count($series) * ($f + 8);
        $padB = $f * 2.6;
        $W = $S['w'];
        $H = $S['h'] + ($size === 'compact' ? count($series) * ($f + 8) : 0);
        $pw = $W - $padL - $padR;
        $ph = $H - $padT - $padB;
        $max = max(array_map(fn ($s) => max($s['values']), $series));
        [$top, $step] = self::niceMax($max, $S['ticks']);
        $n = count($x);
        $xp = fn (int $i) => $padL + ($n > 1 ? $i * $pw / ($n - 1) : $pw / 2);
        $yp = fn (float $v) => $padT + $ph - ($v / $top) * $ph;

        $o = '';
        // Grelha e eixo Y
        for ($v = 0; $v <= $top + 0.0001; $v += $step) {
            $y = round($yp($v), 1);
            $o .= '<line class="ch-grid'.($v == 0 ? ' ch-base' : '').'" x1="'.$padL.'" x2="'.($W - $padR).'" y1="'.$y.'" y2="'.$y.'"/>';
            $o .= '<text class="ch-tick" x="'.($padL - 8).'" y="'.($y + $f * .35).'" text-anchor="end">'.self::e(self::short($v)).'</text>';
        }
        // Eixo X
        foreach ($x as $i => $year) {
            if ($i % $S['xEvery'] === 0) {
                $o .= '<text class="ch-tick" x="'.round($xp($i), 1).'" y="'.($H - $padB + $f * 1.5).'" text-anchor="middle">'.$year.'</text>';
            }
        }
        // Linhas
        foreach ($series as $s) {
            $pts = [];
            foreach ($s['values'] as $i => $v) {
                $pts[] = round($xp($i), 1).','.round($yp($v), 1);
            }
            $o .= '<polyline class="ch-line '.$s['class'].'" points="'.implode(' ', $pts).'"'.(isset($s['dash']) ? ' stroke-dasharray="'.$s['dash'].'"' : '').'/>';
        }
        // Rótulos directos (largo) ou legenda por cima (telemóvel)
        if ($size === 'wide') {
            $ends = [];
            foreach ($series as $k => $s) {
                $ends[$k] = $yp(end($s['values']));
            }
            asort($ends);
            $prev = -INF;
            foreach ($ends as $k => $y) {
                $y = max($y, $prev + $f + 4);
                $prev = $y;
                $s = $series[$k];
                $o .= '<line class="ch-line '.$s['class'].'" x1="'.($W - $padR + 8).'" x2="'.($W - $padR + 24).'" y1="'.round($y, 1).'" y2="'.round($y, 1).'"'.(isset($s['dash']) ? ' stroke-dasharray="'.$s['dash'].'"' : '').'/>';
                $o .= '<text class="ch-label" x="'.($W - $padR + 30).'" y="'.round($y + $f * .35, 1).'">'.self::e($s['label']).'</text>';
            }
        } else {
            foreach ($series as $k => $s) {
                $y = 6 + $k * ($f + 8) + $f * .5;
                $o .= '<line class="ch-line '.$s['class'].'" x1="'.$padL.'" x2="'.($padL + 22).'" y1="'.$y.'" y2="'.$y.'"'.(isset($s['dash']) ? ' stroke-dasharray="'.$s['dash'].'"' : '').'/>';
                $o .= '<text class="ch-label" x="'.($padL + 30).'" y="'.($y + $f * .35).'">'.self::e($s['label']).'</text>';
            }
        }
        // Zonas de toque por ano (tooltip com todas as séries)
        $colW = $n > 1 ? $pw / ($n - 1) : $pw;
        foreach ($x as $i => $year) {
            $tip = $year.': '.implode(' · ', array_map(fn ($s) => $s['label'].' '.self::num($s['values'][$i]).' '.$unit, $series));
            $o .= '<rect class="ch-hit" x="'.round($xp($i) - $colW / 2, 1).'" y="'.$padT.'" width="'.round($colW, 1).'" height="'.$ph.'" data-tip="'.self::e($tip).'" data-x="'.round($xp($i), 1).'"><title>'.self::e($tip).'</title></rect>';
        }
        $o .= '<line class="ch-cross" x1="0" x2="0" y1="'.$padT.'" y2="'.($padT + $ph).'"/>';

        return self::wrap($W, $H, $o, $size);
    }

    /**
     * Barras horizontais ordenadas (uma série).
     * @param array<string, float> $items rótulo => valor
     */
    public static function hbars(array $items, callable $label, string $size, string $class = 'c-s2'): string
    {
        arsort($items);
        $S = self::SIZES[$size];
        $f = $S['font'];
        $wide = $size === 'wide';
        $barH = $wide ? 22 : 16;
        $gap = $wide ? 12 : 30; // no telemóvel o nome fica por cima da barra
        $labelW = $wide ? 250 : 0;
        $valW = $wide ? 140 : 110;
        $W = $S['w'];
        $padT = $wide ? 8 : $f + 10;
        $H = $padT + count($items) * ($barH + $gap);
        $max = max($items) ?: 1;
        $pw = $W - $labelW - $valW - 8;

        $o = '';
        $i = 0;
        foreach ($items as $name => $v) {
            $y = $padT + $i * ($barH + $gap);
            $w = max(2, $v / $max * $pw);
            $tip = $name.': '.$label($v);
            if ($wide) {
                $o .= '<text class="ch-label" x="'.($labelW - 10).'" y="'.($y + $barH * .68).'" text-anchor="end">'.self::e($name).'</text>';
            } else {
                $o .= '<text class="ch-label" x="0" y="'.($y - 6).'">'.self::e($name).'</text>';
            }
            $o .= '<rect class="ch-bar '.$class.'" x="'.$labelW.'" y="'.$y.'" width="'.round($w, 1).'" height="'.$barH.'" rx="2" data-tip="'.self::e($tip).'"><title>'.self::e($tip).'</title></rect>';
            $o .= '<text class="ch-value" x="'.round($labelW + $w + 8, 1).'" y="'.($y + $barH * .68).'">'.self::e($label($v)).'</text>';
            $i++;
        }

        return self::wrap($W, $H, $o, $size);
    }

    /**
     * Barra única a 100 % dividida em partes (ex.: permaneceu / perdida).
     * @param list<array{label: string, value: float, class: string}> $parts
     */
    public static function split(array $parts, string $unit, string $size): string
    {
        $S = self::SIZES[$size];
        $f = $S['font'];
        $W = $S['w'];
        $barH = $size === 'wide' ? 56 : 44;
        $total = array_sum(array_column($parts, 'value')) ?: 1;
        $rows = count($parts);
        $H = 10 + $barH + 18 + $rows * ($f * 2 + 14);
        $o = '';
        $x = 0;
        foreach ($parts as $k => $p) {
            $w = $p['value'] / $total * $W;
            $pct = self::num($p['value'] / $total * 100, 1).'%';
            $tip = $p['label'].': '.self::num($p['value']).' '.$unit.' ('.$pct.')';
            $o .= '<rect class="ch-bar '.$p['class'].'" x="'.round($x + ($k ? 1 : 0), 1).'" y="10" width="'.round(max(2, $w - ($k ? 1 : 0) - ($k < $rows - 1 ? 1 : 0)), 1).'" height="'.$barH.'" rx="2" data-tip="'.self::e($tip).'"><title>'.self::e($tip).'</title></rect>';
            $x += $w;
        }
        // Legenda com valores (texto em tinta normal, quadrado com a cor)
        foreach ($parts as $k => $p) {
            $y = 10 + $barH + 22 + $k * ($f * 2 + 14);
            $pct = self::num($p['value'] / $total * 100, 1).'%';
            $o .= '<rect class="ch-bar '.$p['class'].'" x="0" y="'.($y - $f + 1).'" width="'.$f.'" height="'.$f.'" rx="2"/>';
            $o .= '<text class="ch-label" x="'.($f + 8).'" y="'.$y.'"><tspan class="ch-strong">'.self::e($pct).'</tspan> '.self::e($p['label']).'</text>';
            $o .= '<text class="ch-tick" x="'.($f + 8).'" y="'.($y + $f + 4).'">'.self::e(self::num($p['value']).' '.$unit).'</text>';
        }

        return self::wrap($W, $H, $o, $size);
    }

    protected static function wrap(int|float $w, int|float $h, string $inner, string $size): string
    {
        return '<svg class="chart__svg chart__svg--'.$size.'" viewBox="0 0 '.round($w).' '.round($h).'" width="'.round($w).'" height="'.round($h).'" role="presentation" focusable="false" xmlns="http://www.w3.org/2000/svg">'.$inner.'</svg>';
    }
}
