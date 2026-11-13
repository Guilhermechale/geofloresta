<?php

namespace App\Geo\Writers;

use App\Geo\Geometry;

/** GeoJSON (RFC 7946): WGS 84, exterior anti-horário, 6 casas decimais. */
class GeoJsonWriter implements FeatureWriter
{
    protected $fh;

    protected string $path;

    protected bool $first = true;

    public function __construct(protected int $precision = 6) {}

    public function open(string $basePath, array $fields, string $geometryType, string $layer): void
    {
        $this->path = $basePath.'.geojson';
        $this->fh = fopen($this->path, 'wb');
        fwrite($this->fh, '{"type":"FeatureCollection","name":'.json_encode($layer).',"features":['."\n");
    }

    public function write(array $props, ?array $geom): void
    {
        if ($geom && in_array($geom['type'], ['Polygon', 'MultiPolygon'], true)) {
            $geom = Geometry::orient(Geometry::toMulti($geom), outerClockwise: false);
        }
        if ($geom) {
            $geom['coordinates'] = $geom['type'] === 'Point'
                ? [round($geom['coordinates'][0], $this->precision), round($geom['coordinates'][1], $this->precision)]
                : Geometry::round($geom['coordinates'], $this->precision);
        }
        $f = ['type' => 'Feature', 'properties' => $props, 'geometry' => $geom];
        fwrite($this->fh, ($this->first ? '' : ",\n").json_encode($f, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
        $this->first = false;
    }

    public function close(): array
    {
        fwrite($this->fh, "\n]}\n");
        fclose($this->fh);

        return [$this->path];
    }
}
