<?php

namespace App\Support;

use App\Geo\Geometry;

/**
 * Mini-mapa de pré-visualização em SVG (sem JavaScript, funciona nos dois temas via currentColor/variáveis).
 * Projecção equirectangular com correcção cos(latitude média), graticule de 2° e coordenadas em mono.
 */
class SvgPreview
{
    public static function render(?string $geojson, string $label, int $width = 420): ?string
    {
        $fc = $geojson ? json_decode($geojson, true) : null;
        if (! $fc || empty($fc['features'])) {
            return null;
        }
        $bbox = null;
        foreach ($fc['features'] as $f) {
            if (! empty($f['geometry'])) {
                $bbox = Geometry::mergeBbox($bbox, Geometry::bbox($f['geometry']));
            }
        }
        if (! $bbox) {
            return null;
        }
        [$x0, $y0, $x1, $y1] = $bbox;
        $pad = max($x1 - $x0, $y1 - $y0) * 0.06;
        $x0 -= $pad; $x1 += $pad; $y0 -= $pad; $y1 += $pad;
        $k = cos(deg2rad(($y0 + $y1) / 2));
        $w = ($x1 - $x0) * $k;
        $h = $y1 - $y0;
        $height = (int) round($width * $h / max($w, 1e-9));
        $sx = $width / max($w, 1e-9);
        $px = fn ($x) => round(($x - $x0) * $k * $sx, 1);
        $py = fn ($y) => round(($y1 - $y) * $sx, 1);

        $paths = '';
        foreach ($fc['features'] as $f) {
            $g = $f['geometry'] ?? null;
            if (! $g) {
                continue;
            }
            if ($g['type'] === 'Point') {
                $paths .= '<circle cx="'.$px($g['coordinates'][0]).'" cy="'.$py($g['coordinates'][1]).'" r="1.5" class="pv-pt"/>';
                continue;
            }
            $d = '';
            foreach (Geometry::toMulti($g)['coordinates'] as $poly) {
                foreach ($poly as $ring) {
                    $d .= 'M'.implode('L', array_map(fn ($p) => $px($p[0]).' '.$py($p[1]), $ring)).'Z';
                }
            }
            $paths .= '<path d="'.$d.'" class="pv-area"/>';
        }

        $grid = '';
        for ($lon = ceil($x0 / 2) * 2; $lon <= $x1; $lon += 2) {
            $x = $px($lon);
            $grid .= '<line x1="'.$x.'" y1="0" x2="'.$x.'" y2="'.$height.'"/><text x="'.($x + 3).'" y="'.($height - 4).'">'.abs($lon).'°'.($lon >= 0 ? 'E' : 'W').'</text>';
        }
        for ($lat = ceil($y0 / 2) * 2; $lat <= $y1; $lat += 2) {
            $y = $py($lat);
            $grid .= '<line x1="0" y1="'.$y.'" x2="'.$width.'" y2="'.$y.'"/><text x="3" y="'.($y - 3).'">'.abs($lat).'°'.($lat >= 0 ? 'N' : 'S').'</text>';
        }

        return '<svg class="preview-map" viewBox="0 0 '.$width.' '.$height.'" role="img" aria-label="'.e($label).'" xmlns="http://www.w3.org/2000/svg">'
            .'<g class="pv-grid">'.$grid.'</g><g class="pv-data">'.$paths.'</g></svg>';
    }
}
