<?php

namespace App\Console\Commands;

use App\Data\Builders\DistrictsBuilder;
use App\Data\Builders\FiresBuilder;
use App\Data\Builders\ProvincesBuilder;
use App\Data\DatasetBuilder;
use Illuminate\Console\Command;

/**
 * Gera no servidor os datasets vectoriais/tabulares leves e regista-os no catálogo.
 *   php artisan geofloresta:datasets provinces
 *   php artisan geofloresta:datasets fires --year=2025
 *   php artisan geofloresta:datasets all --publish
 * Datasets raster (WorldCover, Hansen): pipeline/ no computador do utilizador + geofloresta:attach.
 */
class BuildDatasets extends Command
{
    protected $signature = 'geofloresta:datasets {which=all : provinces|districts|fires|all} {--year=2025} {--publish : publicar logo (por defeito fica em rascunho)}';

    protected $description = 'Gera os datasets vectoriais (províncias, distritos, focos de incêndio) com ZIP, LEIAME, licença e checksum';

    public function handle(): int
    {
        ini_set('memory_limit', '1536M');
        $which = $this->argument('which');
        /** @var DatasetBuilder[] $builders */
        $builders = array_filter([
            'provinces' => in_array($which, ['provinces', 'all'], true) ? new ProvincesBuilder : null,
            'districts' => in_array($which, ['districts', 'all'], true) ? new DistrictsBuilder : null,
            'fires' => in_array($which, ['fires', 'all'], true) ? new FiresBuilder((int) $this->option('year')) : null,
        ]);
        if (! $builders) {
            $this->error("Desconhecido: {$which}");

            return self::FAILURE;
        }
        foreach ($builders as $key => $b) {
            $this->info("== {$key}");
            $b->out = $this->output;
            $t = microtime(true);
            $dataset = $b->run();
            if ($this->option('publish')) {
                $dataset->update(['status' => 'published']);
            }
            $this->line(sprintf('  dataset #%d «%s» (%s) em %.1f s', $dataset->id, $dataset->getTranslation('title', 'pt'), $dataset->status, microtime(true) - $t));
        }

        return self::SUCCESS;
    }
}
