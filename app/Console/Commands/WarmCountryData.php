<?php

namespace App\Console\Commands;

use App\Geo\World;
use App\Support\OpenData;
use Illuminate\Console\Command;

/** Pré-carrega (cache de 30 dias) as ligações das fontes abertas de todos os países. */
class WarmCountryData extends Command
{
    protected $signature = 'geofloresta:countries-cache {--only= : ISO3 separados por vírgula}';

    protected $description = 'Pré-carrega as fontes de dados abertas de cada país (geoBoundaries, WorldPop, GFW)';

    public function handle(): int
    {
        $only = array_filter(array_map('strtoupper', explode(',', (string) $this->option('only'))));
        $n = 0;
        foreach (World::all() as $iso => $c) {
            if ($only && ! in_array($iso, $only, true)) {
                continue;
            }
            OpenData::warm($c);
            $n++;
            if ($n % 20 === 0) {
                $this->line("{$n} países…");
            }
        }
        $this->info("{$n} países pré-carregados.");

        return self::SUCCESS;
    }
}
