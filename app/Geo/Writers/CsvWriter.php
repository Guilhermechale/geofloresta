<?php

namespace App\Geo\Writers;

/** CSV UTF-8 (com BOM para abrir bem no Excel), vírgula, ponto decimal. Pontos: colunas longitude/latitude. */
class CsvWriter implements FeatureWriter
{
    protected $fh;

    protected string $path;

    protected array $names = [];

    protected bool $withXY = false;

    public function open(string $basePath, array $fields, string $geometryType, string $layer): void
    {
        $this->path = $basePath.'.csv';
        $this->fh = fopen($this->path, 'wb');
        fwrite($this->fh, "\xEF\xBB\xBF");
        $this->names = array_map(fn (Field $f) => $f->name, $fields);
        $this->withXY = $geometryType === 'Point';
        fputcsv($this->fh, $this->withXY ? [...$this->names, 'longitude', 'latitude'] : $this->names, ',', '"', '');
    }

    public function write(array $props, ?array $geom): void
    {
        $row = array_map(fn ($n) => $props[$n] ?? '', $this->names);
        if ($this->withXY) {
            $row[] = $geom['coordinates'][0] ?? '';
            $row[] = $geom['coordinates'][1] ?? '';
        }
        fputcsv($this->fh, $row, ',', '"', '');
    }

    public function close(): array
    {
        fclose($this->fh);

        return [$this->path];
    }
}
