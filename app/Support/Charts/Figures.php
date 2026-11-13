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
                    $series[] = ['label' => self::t('Limiar')." {$t}%", 'values' => $d['loss'][$t]['annual'], 'class' => $cls] + ($dash ? ['dash' => $dash] : []);
                }
                $svg = fn ($size) => SvgChart::lines($years, $series, $ha, $size);
                $table = self::table([self::t('Ano'), self::t('Limiar').' 10% (ha)', self::t('Limiar').' 30% (ha)', self::t('Limiar').' 50% (ha)'],
                    array_map(fn ($i) => [$years[$i], ...array_map(fn ($s) => SvgChart::num($s['values'][$i]), $series)], array_keys($years)));
                $label = self::t('Gráfico de linhas: perda anual de cobertura arbórea em Moçambique de 2001 a 2025 para os limiares de 10, 30 e 50% de densidade de copa.');
                break;

            case 'gfw-destino-2010-limiar30':
                $ext = $d['loss'][30]['extent_2010'];
                $lost = array_sum(array_slice($d['loss'][30]['annual'], 10));
                $parts = [
                    ['label' => self::t('Permaneceu em 2025'), 'value' => $ext - $lost, 'class' => 'c-c1'],
                    ['label' => self::t('Perdida entre 2011 e 2025'), 'value' => $lost, 'class' => 'c-c2'],
                ];
                $svg = fn ($size) => SvgChart::split($parts, $ha, $size);
                $table = self::table([self::t('Destino'), self::t('Área (ha)'), '%'], array_map(fn ($p) => [$p['label'], SvgChart::num($p['value']), SvgChart::num($p['value'] / $ext * 100, 1).'%'], $parts));
                $label = sprintf(self::t('Barra dividida: da cobertura arbórea existente em 2010 (limiar 30%%), %s%% permaneceu em 2025 e %s%% foi perdida entre 2011 e 2025.'), SvgChart::num(($ext - $lost) / $ext * 100, 1), SvgChart::num($lost / $ext * 100, 1));
                break;

            case 'gfw-vetores-dominantes':
                $series = [
                    ['label' => self::t('Agricultura permanente'), 'values' => $d['drivers']['Agricultura permanente'], 'class' => 'c-c1'],
                    ['label' => self::t('Agricultura itinerante'), 'values' => $d['drivers']['Agricultura itinerante'], 'class' => 'c-c2', 'dash' => '7 4'],
                ];
                $svg = fn ($size) => SvgChart::lines($years, $series, $ha, $size);
                $table = self::table([self::t('Ano'), self::t('Agricultura permanente').' (ha)', self::t('Agricultura itinerante').' (ha)'],
                    array_map(fn ($i) => [$years[$i], SvgChart::num($series[0]['values'][$i]), SvgChart::num($series[1]['values'][$i])], array_keys($years)));
                $label = self::t('Gráfico de linhas: perda anual de cobertura arbórea atribuída à agricultura permanente e à agricultura itinerante em Moçambique, 2001 a 2025.');
                break;

            case 'gfw-vetores-distribuicao':
                $tot = []; foreach ($d['drivers'] as $k => $v) { $tot[self::t($k)] = array_sum($v); }
                $sum = array_sum($tot) ?: 1;
                $pct = fn ($v) => SvgChart::num($v / $sum * 100, 1).'%';
                $svg = fn ($size) => SvgChart::hbars($tot, $pct, $size);
                arsort($tot);
                $table = self::table([self::t('Vetor'), self::t('Perda acumulada (ha)'), '%'], array_map(fn ($k, $v) => [$k, SvgChart::num($v), $pct($v)], array_keys($tot), $tot));
                $label = sprintf(self::t('Barras horizontais: percentagem da perda acumulada de cobertura arbórea por vetor, de agricultura permanente (%s) a commodities duras.'), $pct($tot[self::t('Agricultura permanente')] ?? 0));
                break;

            case 'gfw-perda-provincias':
                $p = $d['provinces_loss_30'];
                $svg = fn ($size) => SvgChart::hbars($p, $fmtHa, $size);
                $table = self::table([self::t('Província'), self::t('Perda acumulada (ha)')], array_map(fn ($k, $v) => [$k, SvgChart::num($v)], array_keys($p), $p));
                $label = self::t('Barras horizontais: perda acumulada de cobertura arbórea por província de Moçambique, 2001 a 2025, limiar de 30%.');
                break;

            default:
                return null;
        }

        return '<div class="chart" role="img" aria-label="'.e($label).'">'
            .$svg('wide').$svg('compact')
            .'<div class="chart__tip" hidden></div>'
            .'</div>'
            .'<details class="chart__data"><summary>'.e(self::t('Ver os dados')).'</summary>'.$table
            .'<p class="chart__source">'.e(self::t('Fonte dos dados: Global Forest Watch / Global Nature Watch (Hansen et al., 2013; Sims et al., 2025), licença CC BY 4.0.')).'</p></details>';
    }

    /** Textos dos gráficos no idioma da página (o português é o texto de origem). */
    protected const I18N = [
        'fr' => [
            'Limiar' => 'Seuil', 'Ano' => 'Année', 'Destino' => 'Devenir', 'Área (ha)' => 'Surface (ha)', 'Vetor' => 'Moteur',
            'Perda acumulada (ha)' => 'Perte cumulée (ha)', 'Província' => 'Province', 'Ver os dados' => 'Voir les données',
            'Permaneceu em 2025' => 'Restée en 2025', 'Perdida entre 2011 e 2025' => 'Perdue entre 2011 et 2025',
            'Agricultura permanente' => 'Agriculture permanente', 'Agricultura itinerante' => 'Agriculture itinérante',
            'Incêndios florestais' => 'Incendies de forêt', 'Exploração madeireira' => 'Exploitation forestière',
            'Assentamentos e infraestruturas' => 'Établissements et infrastructures', 'Outras perturbações naturais' => 'Autres perturbations naturelles',
            'Commodities duras' => 'Matières premières',
            'Gráfico de linhas: perda anual de cobertura arbórea em Moçambique de 2001 a 2025 para os limiares de 10, 30 e 50% de densidade de copa.' => 'Graphique en courbes : perte annuelle de couvert arboré au Mozambique de 2001 à 2025 pour les seuils de 10, 30 et 50 % de densité de canopée.',
            'Barra dividida: da cobertura arbórea existente em 2010 (limiar 30%%), %s%% permaneceu em 2025 e %s%% foi perdida entre 2011 e 2025.' => 'Barre divisée : du couvert arboré existant en 2010 (seuil de 30 %%), %s %% est resté en 2025 et %s %% a été perdu entre 2011 et 2025.',
            'Gráfico de linhas: perda anual de cobertura arbórea atribuída à agricultura permanente e à agricultura itinerante em Moçambique, 2001 a 2025.' => 'Graphique en courbes : perte annuelle de couvert arboré attribuée à l’agriculture permanente et à l’agriculture itinérante au Mozambique, 2001 à 2025.',
            'Barras horizontais: percentagem da perda acumulada de cobertura arbórea por vetor, de agricultura permanente (%s) a commodities duras.' => 'Barres horizontales : part de la perte cumulée de couvert arboré par moteur, de l’agriculture permanente (%s) aux matières premières.',
            'Barras horizontais: perda acumulada de cobertura arbórea por província de Moçambique, 2001 a 2025, limiar de 30%.' => 'Barres horizontales : perte cumulée de couvert arboré par province du Mozambique, 2001 à 2025, seuil de 30 %.',
            'Fonte dos dados: Global Forest Watch / Global Nature Watch (Hansen et al., 2013; Sims et al., 2025), licença CC BY 4.0.' => 'Source des données : Global Forest Watch / Global Nature Watch (Hansen et al., 2013 ; Sims et al., 2025), licence CC BY 4.0.',
        ],
        'en' => [
            'Limiar' => 'Threshold', 'Ano' => 'Year', 'Destino' => 'Fate', 'Área (ha)' => 'Area (ha)', 'Vetor' => 'Driver',
            'Perda acumulada (ha)' => 'Cumulative loss (ha)', 'Província' => 'Province', 'Ver os dados' => 'View the data',
            'Permaneceu em 2025' => 'Remaining in 2025', 'Perdida entre 2011 e 2025' => 'Lost between 2011 and 2025',
            'Agricultura permanente' => 'Permanent agriculture', 'Agricultura itinerante' => 'Shifting cultivation',
            'Incêndios florestais' => 'Wildfire', 'Exploração madeireira' => 'Logging',
            'Assentamentos e infraestruturas' => 'Settlements and infrastructure', 'Outras perturbações naturais' => 'Other natural disturbances',
            'Commodities duras' => 'Hard commodities',
            'Gráfico de linhas: perda anual de cobertura arbórea em Moçambique de 2001 a 2025 para os limiares de 10, 30 e 50% de densidade de copa.' => 'Line chart: annual tree cover loss in Mozambique from 2001 to 2025 for the 10, 30 and 50% canopy density thresholds.',
            'Barra dividida: da cobertura arbórea existente em 2010 (limiar 30%%), %s%% permaneceu em 2025 e %s%% foi perdida entre 2011 e 2025.' => 'Split bar: of the tree cover present in 2010 (30%% threshold), %s%% remained in 2025 and %s%% was lost between 2011 and 2025.',
            'Gráfico de linhas: perda anual de cobertura arbórea atribuída à agricultura permanente e à agricultura itinerante em Moçambique, 2001 a 2025.' => 'Line chart: annual tree cover loss attributed to permanent agriculture and shifting cultivation in Mozambique, 2001 to 2025.',
            'Barras horizontais: percentagem da perda acumulada de cobertura arbórea por vetor, de agricultura permanente (%s) a commodities duras.' => 'Horizontal bars: share of cumulative tree cover loss by driver, from permanent agriculture (%s) to hard commodities.',
            'Barras horizontais: perda acumulada de cobertura arbórea por província de Moçambique, 2001 a 2025, limiar de 30%.' => 'Horizontal bars: cumulative tree cover loss by province of Mozambique, 2001 to 2025, 30% threshold.',
            'Fonte dos dados: Global Forest Watch / Global Nature Watch (Hansen et al., 2013; Sims et al., 2025), licença CC BY 4.0.' => 'Data source: Global Forest Watch / Global Nature Watch (Hansen et al., 2013; Sims et al., 2025), CC BY 4.0 licence.',
        ],
    ];

    protected static function t(string $pt): string
    {
        return self::I18N[app()->getLocale()][$pt] ?? $pt;
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
