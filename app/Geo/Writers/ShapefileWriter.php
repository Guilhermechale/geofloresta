<?php

namespace App\Geo\Writers;

use App\Geo\Geometry;

/**
 * ESRI Shapefile (.shp .shx .dbf .prj .cpg) em streaming. Pontos (tipo 1) ou polígonos (tipo 5).
 * Limitações do formato (avisadas no LEIAME): nomes de campo até 10 caracteres, texto até 254 bytes,
 * vários ficheiros, 2 GB por ficheiro.
 */
class ShapefileWriter implements FeatureWriter
{
    public const PRJ = 'GEOGCS["GCS_WGS_1984",DATUM["D_WGS_1984",SPHEROID["WGS_1984",6378137.0,298.257223563]],PRIMEM["Greenwich",0.0],UNIT["Degree",0.0174532925199433]]';

    protected $shp;

    protected $shx;

    protected $dbf;

    protected string $base;

    protected array $fields;

    protected int $shapeType;

    protected int $count = 0;

    protected int $offsetWords = 50; // cabeçalho = 100 bytes = 50 palavras de 16 bits

    protected ?array $bbox = null;

    protected int $recordLength = 1;

    public function open(string $basePath, array $fields, string $geometryType, string $layer): void
    {
        $this->base = $basePath;
        $this->fields = $fields;
        $this->shapeType = $geometryType === 'Point' ? 1 : 5;
        $this->shp = fopen($basePath.'.shp', 'w+b');
        $this->shx = fopen($basePath.'.shx', 'w+b');
        $this->dbf = fopen($basePath.'.dbf', 'w+b');
        fwrite($this->shp, str_repeat("\0", 100));
        fwrite($this->shx, str_repeat("\0", 100));

        // Cabeçalho DBF (dBase III), número de registos corrigido no fecho
        foreach ($fields as $f) {
            $this->recordLength += $this->dbfLength($f);
        }
        $headerLength = 32 + 32 * count($fields) + 1;
        $now = getdate();
        fwrite($this->dbf, pack('CCCCVvv', 0x03, $now['year'] - 1900, $now['mon'], $now['mday'], 0, $headerLength, $this->recordLength).str_repeat("\0", 20));
        foreach ($fields as $f) {
            $type = match ($f->type) { 'int', 'float' => 'N', 'date' => 'D', default => 'C' };
            fwrite($this->dbf, str_pad(strtoupper($f->name), 11, "\0").$type.str_repeat("\0", 4)
                .pack('CC', $this->dbfLength($f), $f->type === 'float' ? $f->decimals : 0).str_repeat("\0", 14));
        }
        fwrite($this->dbf, "\x0D");
    }

    protected function dbfLength(Field $f): int
    {
        return match ($f->type) { 'date' => 8, 'string' => min(254, $f->length), default => min(20, $f->length) };
    }

    public function write(array $props, ?array $geom): void
    {
        $this->count++;
        if ($this->shapeType === 1) {
            [$x, $y] = $geom['coordinates'];
            $content = pack('V', 1).pack('ee', $x, $y);
            $this->bbox = Geometry::mergeBbox($this->bbox, [$x, $y, $x, $y]);
        } else {
            $multi = Geometry::orient(Geometry::toMulti($geom), outerClockwise: true);
            $bb = Geometry::bbox($multi);
            $this->bbox = Geometry::mergeBbox($this->bbox, $bb);
            $parts = [];
            $points = '';
            $n = 0;
            foreach ($multi['coordinates'] as $poly) {
                foreach ($poly as $ring) {
                    $parts[] = $n;
                    foreach ($ring as $p) {
                        $points .= pack('ee', $p[0], $p[1]);
                        $n++;
                    }
                }
            }
            $content = pack('V', 5).pack('eeee', $bb[0], $bb[1], $bb[2], $bb[3]).pack('VV', count($parts), $n)
                .pack('V*', ...$parts).$points;
        }
        $words = intdiv(strlen($content), 2);
        fwrite($this->shp, pack('NN', $this->count, $words).$content);
        fwrite($this->shx, pack('NN', $this->offsetWords, $words));
        $this->offsetWords += 4 + $words;

        // Registo DBF
        $rec = ' ';
        foreach ($this->fields as $f) {
            $len = $this->dbfLength($f);
            $v = $props[$f->name] ?? null;
            if ($f->type === 'string') {
                $s = mb_strcut((string) $v, 0, $len, 'UTF-8');
                $rec .= $s.str_repeat(' ', $len - strlen($s));
            } elseif ($f->type === 'date') {
                $rec .= $v ? str_replace('-', '', substr((string) $v, 0, 10)) : str_repeat(' ', 8);
            } else {
                $s = $v === null || $v === '' ? '' : ($f->type === 'float' ? number_format((float) $v, $f->decimals, '.', '') : (string) (int) $v);
                $rec .= str_pad(substr($s, 0, $len), $len, ' ', STR_PAD_LEFT);
            }
        }
        fwrite($this->dbf, $rec);
    }

    protected function header($fh, int $lengthWords): void
    {
        $bb = $this->bbox ?? [0, 0, 0, 0];
        fseek($fh, 0);
        fwrite($fh, pack('N', 9994).str_repeat("\0", 20).pack('N', $lengthWords).pack('VV', 1000, $this->shapeType)
            .pack('eeee', $bb[0], $bb[1], $bb[2], $bb[3]).pack('eeee', 0, 0, 0, 0));
    }

    public function close(): array
    {
        $this->header($this->shp, $this->offsetWords);
        $this->header($this->shx, 50 + 4 * $this->count);
        fseek($this->dbf, 4);
        fwrite($this->dbf, pack('V', $this->count));
        fseek($this->dbf, 0, SEEK_END);
        fwrite($this->dbf, "\x1A");
        fclose($this->shp);
        fclose($this->shx);
        fclose($this->dbf);
        file_put_contents($this->base.'.prj', self::PRJ);
        file_put_contents($this->base.'.cpg', 'UTF-8');

        return array_map(fn ($e) => $this->base.'.'.$e, ['shp', 'shx', 'dbf', 'prj', 'cpg']);
    }
}
