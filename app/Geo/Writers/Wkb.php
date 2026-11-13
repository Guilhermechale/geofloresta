<?php

namespace App\Geo\Writers;

/** Well-Known Binary (little-endian) para Point e MultiPolygon. */
class Wkb
{
    public static function encode(array $geom): string
    {
        return match ($geom['type']) {
            'Point' => pack('CV', 1, 1).pack('ee', $geom['coordinates'][0], $geom['coordinates'][1]),
            'Polygon' => self::polygon($geom['coordinates']),
            'MultiPolygon' => pack('CVV', 1, 6, count($geom['coordinates'])).implode('', array_map([self::class, 'polygon'], $geom['coordinates'])),
            default => throw new \InvalidArgumentException('WKB: tipo não suportado '.$geom['type']),
        };
    }

    protected static function polygon(array $rings): string
    {
        $out = pack('CVV', 1, 3, count($rings));
        foreach ($rings as $ring) {
            $out .= pack('V', count($ring));
            foreach ($ring as $p) {
                $out .= pack('ee', $p[0], $p[1]);
            }
        }

        return $out;
    }
}
