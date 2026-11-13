<?php

namespace App\Console\Commands;

use App\Geo\DatasetPackager;
use App\Models\Dataset;
use Illuminate\Console\Command;

/**
 * Regista ficheiros produzidos pelo pipeline (no computador do utilizador) e enviados por SSH.
 *   php artisan geofloresta:attach cobertura-do-solo-esa-worldcover-2021 ~/entregas/worldcover/sofala/*.tif \
 *       --format=cog --name=worldcover_2021_sofala --label-pt="Sofala" --label-fr="Sofala" --label-en="Sofala" --host
 * Cria um ZIP com LEIAME.txt, LICENCA.txt e CITACAO.txt, calcula tamanho e SHA-256.
 * --host passa o dataset de "Ligação externa" a "Alojado" (só depois de confirmada a licença).
 */
class AttachDatasetFiles extends Command
{
    protected $signature = 'geofloresta:attach {slug : slug PT do dataset} {files* : ficheiros a incluir no ZIP}
        {--format= : cog|geojson|gpkg|shp|csv} {--name= : nome base do ZIP (sem extensão)}
        {--label-pt=} {--label-fr=} {--label-en=} {--sort=0} {--host : marcar o dataset como alojado}';

    protected $description = 'Empacota e regista ficheiros de dados gerados pelo pipeline';

    public function handle(DatasetPackager $packager): int
    {
        $dataset = Dataset::where('slug', 'like', '%"pt":"'.$this->argument('slug').'"%')->first();
        if (! $dataset) {
            $this->error('Dataset não encontrado: '.$this->argument('slug'));

            return self::FAILURE;
        }
        $format = (string) $this->option('format');
        if (! array_key_exists($format, Dataset::FORMATS)) {
            $this->error('Formato inválido. Use: '.implode(', ', array_keys(Dataset::FORMATS)));

            return self::FAILURE;
        }
        $files = array_values(array_filter($this->argument('files'), 'is_file'));
        if (! $files) {
            $this->error('Nenhum ficheiro encontrado.');

            return self::FAILURE;
        }
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) ($this->option('name') ?: pathinfo($files[0], PATHINFO_FILENAME)));
        $label = array_filter(['pt' => $this->option('label-pt'), 'fr' => $this->option('label-fr'), 'en' => $this->option('label-en')]);

        $file = $packager->package($dataset, $format, $files, "{$name}_{$format}.zip", $label ?: null, [], (int) $this->option('sort'));
        if ($this->option('host') && $dataset->hosting !== 'hosted') {
            $dataset->update(['hosting' => 'hosted']);
            $this->warn('Dataset marcado como alojado. Confirme que a licença permite redistribuição (docs/DADOS-E-LICENCAS.md).');
        }
        $this->info(sprintf('%s registado: %s, sha256 %s', $file->filename, format_bytes((int) $file->size_bytes), $file->sha256));

        return self::SUCCESS;
    }
}
