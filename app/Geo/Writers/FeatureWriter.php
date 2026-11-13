<?php

namespace App\Geo\Writers;

/** Escritor de entidades em streaming (uma de cada vez). */
interface FeatureWriter
{
    /** @param Field[] $fields  @param string $geometryType Point | MultiPolygon | None */
    public function open(string $basePath, array $fields, string $geometryType, string $layer): void;

    /** @param array<string,mixed> $props  @param array|null $geom geometria GeoJSON */
    public function write(array $props, ?array $geom): void;

    /** @return string[] ficheiros criados */
    public function close(): array;
}
