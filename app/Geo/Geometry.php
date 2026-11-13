<?php

namespace App\Geo;

/**
 * Operações geométricas mínimas sobre geometrias GeoJSON (arrays), em WGS 84.
 * Suporta Point, Polygon e MultiPolygon. Sem dependências (o servidor não tem GDAL).
 */
class Geometry
{
    /** Converte Polygon em MultiPolygon (formato único para os escritores). */
    public static function toMulti(array $geom): array
    {
        return match ($geom['type']) {
            'Polygon' => ['type' => 'MultiPolygon', 'coordinates' => [$geom['coordinates']]],
            'MultiPolygon', 'Point', 'LineString', 'MultiLineString' => $geom,
            default => throw new \InvalidArgumentException('Tipo de geometria não suportado: '.$geom['type']),
        };
    }

    /** @return array{0:float,1:float,2:float,3:float} [minX, minY, maxX, maxY] */
    public static function bbox(array $geom): array
    {
        $minX = $minY = INF;
        $maxX = $maxY = -INF;
        $walk = function ($c) use (&$walk, &$minX, &$minY, &$maxX, &$maxY) {
            if (is_numeric($c[0] ?? null)) {
                $minX = min($minX, $c[0]); $maxX = max($maxX, $c[0]);
                $minY = min($minY, $c[1]); $maxY = max($maxY, $c[1]);

                return;
            }
            foreach ($c as $sub) {
                $walk($sub);
            }
        };
        $walk($geom['coordinates']);

        return [$minX, $minY, $maxX, $maxY];
    }

    public static function mergeBbox(?array $a, array $b): array
    {
        return $a ? [min($a[0], $b[0]), min($a[1], $b[1]), max($a[2], $b[2]), max($a[3], $b[3])] : $b;
    }

    /** Área com sinal (fórmula do laço): positiva = anti-horário. Em graus², só para orientação/comparação. */
    public static function signedArea(array $ring): float
    {
        $a = 0.0;
        $n = count($ring);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $a += ($ring[$j][0] * $ring[$i][1]) - ($ring[$i][0] * $ring[$j][1]);
        }

        return $a / 2;
    }

    /** Orienta os anéis: exterior no sentido pedido, buracos no oposto. */
    public static function orient(array $multi, bool $outerClockwise): array
    {
        foreach ($multi['coordinates'] as $p => $poly) {
            foreach ($poly as $r => $ring) {
                $cw = self::signedArea($ring) < 0;
                $wantCw = $r === 0 ? $outerClockwise : ! $outerClockwise;
                if ($cw !== $wantCw) {
                    $multi['coordinates'][$p][$r] = array_reverse($ring);
                }
            }
        }

        return $multi;
    }

    protected static function pointInRing(float $x, float $y, array $ring): bool
    {
        $inside = false;
        $n = count($ring);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];
            if ((($yi > $y) !== ($yj > $y)) && ($x < ($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 1e-15) + $xi)) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /** Ponto dentro de Polygon/MultiPolygon (respeita buracos). */
    public static function contains(array $geom, float $x, float $y): bool
    {
        $polys = $geom['type'] === 'Polygon' ? [$geom['coordinates']] : $geom['coordinates'];
        foreach ($polys as $poly) {
            if (! self::pointInRing($x, $y, $poly[0])) {
                continue;
            }
            $inHole = false;
            for ($h = 1, $n = count($poly); $h < $n; $h++) {
                if (self::pointInRing($x, $y, $poly[$h])) {
                    $inHole = true;
                    break;
                }
            }
            if (! $inHole) {
                return true;
            }
        }

        return false;
    }

    /** Douglas–Peucker num anel; mantém o anel fechado e com pelo menos 4 vértices. */
    public static function simplifyRing(array $ring, float $tol): array
    {
        $n = count($ring);
        if ($n <= 4) {
            return $ring;
        }
        $keep = array_fill(0, $n, false);
        $keep[0] = $keep[$n - 1] = true;
        $stack = [[0, $n - 1]];
        $tol2 = $tol * $tol;
        while ($stack) {
            [$s, $e] = array_pop($stack);
            $max = 0.0;
            $idx = -1;
            [$x1, $y1] = $ring[$s];
            [$x2, $y2] = $ring[$e];
            $dx = $x2 - $x1;
            $dy = $y2 - $y1;
            $len2 = $dx * $dx + $dy * $dy;
            for ($i = $s + 1; $i < $e; $i++) {
                [$px, $py] = $ring[$i];
                if ($len2 == 0.0) {
                    $d = ($px - $x1) ** 2 + ($py - $y1) ** 2;
                } else {
                    $t = max(0, min(1, (($px - $x1) * $dx + ($py - $y1) * $dy) / $len2));
                    $d = ($px - ($x1 + $t * $dx)) ** 2 + ($py - ($y1 + $t * $dy)) ** 2;
                }
                if ($d > $max) {
                    $max = $d;
                    $idx = $i;
                }
            }
            if ($idx > 0 && $max > $tol2) {
                $keep[$idx] = true;
                $stack[] = [$s, $idx];
                $stack[] = [$idx, $e];
            }
        }
        $out = [];
        foreach ($ring as $i => $pt) {
            if ($keep[$i]) {
                $out[] = $pt;
            }
        }

        return count($out) >= 4 ? $out : $ring;
    }

    /** Simplifica e arredonda; descarta polígonos que ficariam degenerados. */
    public static function simplify(array $geom, float $tol, int $precision = 5): array
    {
        $multi = self::toMulti($geom);
        $polys = [];
        foreach ($multi['coordinates'] as $poly) {
            $rings = [];
            foreach ($poly as $r => $ring) {
                $s = self::round(self::simplifyRing($ring, $tol), $precision);
                if (count($s) >= 4 && abs(self::signedArea($s)) > 0) {
                    $rings[] = $s;
                } elseif ($r === 0) {
                    continue 2; // exterior degenerado: descarta o polígono
                }
            }
            if ($rings) {
                $polys[] = $rings;
            }
        }

        return ['type' => 'MultiPolygon', 'coordinates' => $polys];
    }

    public static function round(array $coords, int $precision): array
    {
        return array_map(fn ($c) => is_numeric($c[0] ?? null)
            ? [round($c[0], $precision), round($c[1], $precision)]
            : self::round($c, $precision), $coords);
    }

    /** Centróide aproximado (do maior polígono) — para a coluna lon/lat das tabelas. */
    public static function centroid(array $geom): array
    {
        $multi = self::toMulti($geom);
        $best = null;
        $bestArea = -1;
        foreach ($multi['coordinates'] as $poly) {
            $a = abs(self::signedArea($poly[0]));
            if ($a > $bestArea) {
                $bestArea = $a;
                $best = $poly[0];
            }
        }
        $cx = $cy = 0.0;
        $a6 = 0.0;
        $n = count($best);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $f = $best[$j][0] * $best[$i][1] - $best[$i][0] * $best[$j][1];
            $cx += ($best[$j][0] + $best[$i][0]) * $f;
            $cy += ($best[$j][1] + $best[$i][1]) * $f;
            $a6 += $f * 3;
        }

        return $a6 == 0.0 ? [$best[0][0], $best[0][1]] : [round($cx / $a6, 5), round($cy / $a6, 5)];
    }

    /** Área geodésica aproximada em km² (projecção equivalente local; erro < 1% à escala de província). */
    public static function areaKm2(array $geom): float
    {
        $multi = self::toMulti($geom);
        $R = 6371.0088;
        $total = 0.0;
        foreach ($multi['coordinates'] as $poly) {
            foreach ($poly as $r => $ring) {
                $a = 0.0;
                $n = count($ring);
                for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
                    $a += deg2rad($ring[$i][0] - $ring[$j][0]) * (2 + sin(deg2rad($ring[$j][1])) + sin(deg2rad($ring[$i][1])));
                }
                $a = abs($a * $R * $R / 2);
                $total += $r === 0 ? $a : -$a;
            }
        }

        return round($total, 1);
    }
}
