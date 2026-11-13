<?php

namespace App\Support\Charts;

use App\Console\Commands\ImportGfw;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Gráficos que se inserem nos artigos com uma marca no texto: [grafico:nome].
 * Os dados vêm da fonte original (ex.: MOZ.xlsx do Global Forest Watch, importado com geofloresta:gfw).
 */
class Figures
{
    /** Nome => descrição (mostrada no painel). */
    public const CATALOGUE = [
        'gfw-perda-anual-limiares' => 'Perda anual de cobertura arbórea, limiares 10/30/50% (2001–2025)',
        'gfw-destino-2010-limiar30' => 'Destino da cobertura arbórea de 2010 até 2025 (limiar 30%)',
        'gfw-vetores-dominantes' => 'Perda anual pelos dois vetores dominantes (limiar 30%)',
        'gfw-vetores-distribuicao' => 'Distribuição da perda por vetor (2001–2025, limiar 30%)',
        'gfw-perda-provincias' => 'Perda acumulada por província (2001–2025, limiar 30%)',
    ];

    public static function data(): ?array
    {
        $disk = Storage::disk('local');
        if (! $disk->exists(ImportGfw::PATH)) {
            return null;
        }

        return Cache::remember('gfw-moz:'.$disk->lastModified(ImportGfw::PATH), now()->addDay(),
            fn () => json_decode($disk->get(ImportGfw::PATH), true));
    }

    /** HTML do gráfico (sem legenda; a legenda "Figura N." vem do texto do artigo). Null se não existir. */
    public static function render(string $key): ?string
    {
        $d = self::data();
        if (! $d || ! isset(self::CATALOGUE[$key])) {
            return null;
        }
        $years = $d['years'];
        $ha = 'ha';
        $fmtHa = fn ($v) => SvgChart::num($v).' ha';

        switch ($key) {
            case 'gfw-perda-anual-limiares':
                $series = [];
                foreach ([10 => ['c-s1', null], 30 => ['c-s2', '7 4'], 50 => ['c-s3', '2 4']] as $t => [$cls, $dash]) {
                    $series[] = ['label' => "Limiar {$t}%", 'values' => $d['loss'][$t]['annual'], 'class' => $cls] + ($dash ? ['dash' => $dash] : []);
                }
                $svg = fn ($size) => SvgChart::lines($years, $series, $ha, $size);
                $table = self::table(['Ano', 'Limiar 10% (ha)', 'Limiar 30% (ha)', 'Limiar 50% (ha)'],
                    array_map(fn ($i) => [$years[$i], ...array_map(fn ($s) => SvgChart::num($s['values'][$i]), $series)], array_keys($years)));
                $label = 'Gráfico de linhas: perda anual de cobertura arbórea em Moçambique de 2001 a 2025 para os limiares de 10, 30 e 50% de densidade de copa.';
                break;

            case 'gfw-destino-2010-limiar30':
                $ext = $d['loss'][30]['extent_2010'];
                $lost = array_sum(array_slice($d['loss'][30]['annual'], 10));
                $parts = [
                    ['label' => 'Permaneceu em 2025', 'value' => $ext - $lost, 'class' => 'c-c1'],
                    ['label' => 'Perdida entre 2011 e 2025', 'value' => $lost, 'class' => 'c-c2'],
                ];
                $svg = fn ($size) => SvgChart::split($parts, $ha, $size);
                $table = self::table(['Destino', 'Área (ha)', '%'], array_map(fn ($p) => [$p['label'], SvgChart::num($p['value']), SvgChart::num($p['value'] / $ext * 100, 1).'%'], $parts));
                $label = 'Barra dividida: da cobertura arbórea existente em 2010 (limiar 30%), '.SvgChart::num(($ext - $lost) / $ext * 100, 1).'% permaneceu em 2025 e '.SvgChart::num($lost / $ext * 100, 1).'% foi perdida entre 2011 e 2025.';
                break;

            case 'gfw-vetores-dominantes':
                $series = [
                    ['label' => 'Agricultura permanente', 'values' => $d['drivers']['Agricultura permanente'], 'class' => 'c-c1'],
                    ['label' => 'Agricultura itinerante', 'values' => $d['drivers']['Agricultura itinerante'], 'class' => 'c-c2', 'dash' => '7 4'],
                ];
                $svg = fn ($size) => SvgChart::lines($years, $series, $ha, $size);
                $table = self::table(['Ano', 'Agricultura permanente (ha)', 'Agricultura itinerante (ha)'],
                    array_map(fn ($i) => [$years[$i], SvgChart::num($series[0]['values'][$i]), SvgChart::num($series[1]['values'][$i])], array_keys($years)));
                $label = 'Gráfico de linhas: perda anual de cobertura arbórea atribuída à agricultura permanente e à agricultura itinerante em Moçambique, 2001 a 2025.';
                break;

            case 'gfw-vetores-distribuicao':
                $tot = array_map('array_sum', $d['drivers']);
                $sum = array_sum($tot) ?: 1;
                $pct = fn ($v) => SvgChart::num($v / $sum * 100, 1).'%';
                $svg = fn ($size) => SvgChart::hbars($tot, $pct, $size);
                arsort($tot);
                $table = self::table(['Vetor', 'Perda acumulada (ha)', '%'], array_map(fn ($k, $v) => [$k, SvgChart::num($v), $pct($v)], array_keys($tot), $tot));
                $label = 'Barras horizontais: percentagem da perda acumulada de cobertura arbórea por vetor, de agricultura permanente ('.$pct($tot['Agricultura permanente'] ?? 0).') a commodities duras.';
                break;

            case 'gfw-perda-provincias':
                $p = $d['provinces_loss_30'];
                $svg = fn ($size) => SvgChart::hbars($p, $fmtHa, $size);
                $table = self::table(['Província', 'Perda acumulada (ha)'], array_map(fn ($k, $v) => [$k, SvgChart::num($v)], array_keys($p), $p));
                $label = 'Barras horizontais: perda acumulada de cobertura arbórea por província de Moçambique, 2001 a 2025, limiar de 30%.';
                break;

            default:
                return null;
        }

        return '<div class="chart" role="img" aria-label="'.e($label).'">'
            .$svg('wide').$svg('compact')
            .'<div class="chart__tip" hidden></div>'
            .'</div>'
            .'<details class="chart__data"><summary>Ver os dados</summary>'.$table
            .'<p class="chart__source">Fonte dos dados: Global Forest Watch / Global Nature Watch (Hansen et al., 2013; Sims et al., 2025), licença CC BY 4.0.</p></details>';
    }

    protected static function table(array $head, array $rows): string
    {
        $h = '<div class="table-wrap"><table><thead><tr>'.implode('', array_map(fn ($c) => '<th scope="col">'.e($c).'</th>', $head)).'</tr></thead><tbody>';
        foreach ($rows as $r) {
            $h .= '<tr>'.implode('', array_map(fn ($c, $i) => $i === 0 ? '<th scope="row">'.e((string) $c).'</th>' : '<td>'.e((string) $c).'</td>', $r, array_keys($r))).'</tr>';
        }

        return $h.'</tbody></table></div>';
    }
}
