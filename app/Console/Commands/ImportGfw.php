<?php

namespace App\Console\Commands;

use App\Support\Xlsx;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Importa a exportação nacional do Global Forest Watch / Global Nature Watch (MOZ.xlsx)
 * e grava um resumo em JSON usado pelos gráficos dos artigos ([grafico:...]).
 */
class ImportGfw extends Command
{
    protected $signature = 'geofloresta:gfw {file : caminho para MOZ.xlsx}';

    protected $description = 'Importa os dados do Global Forest Watch (Moçambique) para os gráficos dos artigos';

    public const PATH = 'fontes/gfw-moz.json';

    public const DRIVERS = [
        'Permanent agriculture' => 'Agricultura permanente',
        'Shifting cultivation' => 'Agricultura itinerante',
        'Wildfire' => 'Incêndios florestais',
        'Logging' => 'Exploração madeireira',
        'Settlements & Infrastructure' => 'Assentamentos e infraestruturas',
        'Other natural disturbances' => 'Outras perturbações naturais',
        'Hard commodities' => 'Commodities duras',
    ];

    public function handle(): int
    {
        $file = $this->argument('file');
        if (! is_file($file)) {
            $this->error("Ficheiro não encontrado: {$file}");

            return self::FAILURE;
        }
        $sheets = Xlsx::read($file);
        $years = range(2001, 2025);

        // Perda anual por limiar (tabela "Country tree cover loss")
        $loss = [];
        foreach ($sheets['Country tree cover loss'] ?? [] as $r) {
            $t = (int) $r['threshold'];
            $loss[$t] = [
                'extent_2000' => (float) $r['extent_2000_ha'],
                'extent_2010' => (float) $r['extent_2010_ha'],
                'annual' => array_map(fn ($y) => (float) ($r["tc_loss_ha_{$y}"] ?? 0), $years),
            ];
        }

        // Vetores (limiar 30)
        $drivers = [];
        foreach ($sheets['Country drivers'] ?? [] as $r) {
            if ((int) $r['threshold'] !== 30) {
                continue;
            }
            $name = self::DRIVERS[$r['driver']] ?? $r['driver'];
            $drivers[$name] ??= array_fill(0, count($years), 0.0);
            $i = (int) $r['year'] - 2001;
            if (isset($drivers[$name][$i])) {
                $drivers[$name][$i] += (float) $r['tc_loss_ha'];
            }
        }

        // Perda por província (limiar 30), para gráficos futuros
        $provinces = [];
        foreach ($sheets['Subnational 1 tree cover loss'] ?? [] as $r) {
            if ((int) $r['threshold'] === 30) {
                $provinces[$r['subnational1']] = array_sum(array_map(fn ($y) => (float) ($r["tc_loss_ha_{$y}"] ?? 0), $years));
            }
        }
        arsort($provinces);

        $data = [
            'source' => 'Global Forest Watch / Global Nature Watch — exportação nacional de Moçambique (Hansen et al., 2013; Sims et al., 2025)',
            'licence' => 'CC-BY-4.0',
            'imported_at' => now()->toDateString(),
            'years' => $years,
            'loss' => $loss,
            'drivers' => $drivers,
            'provinces_loss_30' => $provinces,
        ];
        Storage::disk('local')->put(self::PATH, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        // Verificação com os números citados nos artigos
        $fmt = fn ($v) => number_format($v, 0, ',', '.');
        foreach ([10, 30, 50] as $t) {
            if (! isset($loss[$t])) {
                continue;
            }
            $total = array_sum($loss[$t]['annual']);
            $recent = array_sum(array_slice($loss[$t]['annual'], 10));
            $this->line(sprintf('Limiar %d%%: perda 2001–2025 = %s ha · extensão 2010 = %s ha · perda 2011–2025 = %s ha (%s%%)',
                $t, $fmt($total), $fmt($loss[$t]['extent_2010']), $fmt($recent), number_format($recent / $loss[$t]['extent_2010'] * 100, 1, ',', '')));
        }
        $sum = array_sum(array_map('array_sum', $drivers));
        foreach ($drivers as $name => $vals) {
            $this->line(sprintf('  %-32s %12s ha  %5s%%', $name, $fmt(array_sum($vals)), number_format(array_sum($vals) / max($sum, 1) * 100, 1, ',', '')));
        }
        $this->line('  Soma dos vetores: '.$fmt($sum).' ha');
        $this->info('Gravado em storage/app/'.self::PATH);

        return self::SUCCESS;
    }
}
