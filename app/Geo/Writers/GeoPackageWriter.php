<?php

namespace App\Geo\Writers;

use App\Geo\Geometry;
use SQLite3;

/**
 * GeoPackage 1.4 (OGC 12-128r19) mínimo e válido: tabelas obrigatórias, SRS 4326,
 * geometria em GeoPackageBinary (cabeçalho "GP" + envelope + WKB). Sem índice R-tree (opcional na norma).
 */
class GeoPackageWriter implements FeatureWriter
{
    protected SQLite3 $db;

    protected string $path;

    protected string $table;

    protected array $fields;

    protected string $geomType;

    protected ?array $bbox = null;

    protected \SQLite3Stmt $insert;

    public const WGS84_WKT = 'GEOGCS["WGS 84",DATUM["WGS_1984",SPHEROID["WGS 84",6378137,298.257223563,AUTHORITY["EPSG","7030"]],AUTHORITY["EPSG","6326"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.0174532925199433,AUTHORITY["EPSG","9122"]],AXIS["Latitude",NORTH],AXIS["Longitude",EAST],AUTHORITY["EPSG","4326"]]';

    public function open(string $basePath, array $fields, string $geometryType, string $layer): void
    {
        $this->path = $basePath.'.gpkg';
        @unlink($this->path);
        $this->table = preg_replace('/[^a-z0-9_]/', '_', strtolower($layer));
        $this->fields = $fields;
        $this->geomType = strtoupper($geometryType === 'Point' ? 'POINT' : 'MULTIPOLYGON');

        $db = $this->db = new SQLite3($this->path);
        $db->exec('PRAGMA application_id = 1196444487'); // "GPKG"
        $db->exec('PRAGMA user_version = 10400');
        $db->exec('PRAGMA journal_mode = OFF');
        $db->exec('PRAGMA synchronous = OFF');
        $db->exec("CREATE TABLE gpkg_spatial_ref_sys (srs_name TEXT NOT NULL, srs_id INTEGER NOT NULL PRIMARY KEY, organization TEXT NOT NULL, organization_coordsys_id INTEGER NOT NULL, definition TEXT NOT NULL, description TEXT)");
        $stmt = $db->prepare('INSERT INTO gpkg_spatial_ref_sys VALUES (?,?,?,?,?,?)');
        foreach ([
            ['Undefined cartesian SRS', -1, 'NONE', -1, 'undefined', 'undefined cartesian coordinate reference system'],
            ['Undefined geographic SRS', 0, 'NONE', 0, 'undefined', 'undefined geographic coordinate reference system'],
            ['WGS 84 geodetic', 4326, 'EPSG', 4326, self::WGS84_WKT, 'longitude/latitude coordinates in decimal degrees on the WGS 84 spheroid'],
        ] as $row) {
            foreach ($row as $i => $v) {
                $stmt->bindValue($i + 1, $v);
            }
            $stmt->execute();
            $stmt->reset();
        }
        $db->exec("CREATE TABLE gpkg_contents (table_name TEXT NOT NULL PRIMARY KEY, data_type TEXT NOT NULL, identifier TEXT UNIQUE, description TEXT DEFAULT '', last_change DATETIME NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now')), min_x DOUBLE, min_y DOUBLE, max_x DOUBLE, max_y DOUBLE, srs_id INTEGER, CONSTRAINT fk_gc_r_srs_id FOREIGN KEY (srs_id) REFERENCES gpkg_spatial_ref_sys(srs_id))");
        $db->exec("CREATE TABLE gpkg_geometry_columns (table_name TEXT NOT NULL, column_name TEXT NOT NULL, geometry_type_name TEXT NOT NULL, srs_id INTEGER NOT NULL, z TINYINT NOT NULL, m TINYINT NOT NULL, CONSTRAINT pk_geom_cols PRIMARY KEY (table_name, column_name), CONSTRAINT fk_gc_tn FOREIGN KEY (table_name) REFERENCES gpkg_contents(table_name), CONSTRAINT fk_gc_srs FOREIGN KEY (srs_id) REFERENCES gpkg_spatial_ref_sys (srs_id))");

        $cols = ['fid INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL', "geom {$this->geomType}"];
        foreach ($fields as $f) {
            $cols[] = '"'.$f->name.'" '.match ($f->type) { 'int' => 'INTEGER', 'float' => 'REAL', 'date' => 'DATE', default => 'TEXT' };
        }
        $db->exec('CREATE TABLE "'.$this->table.'" ('.implode(', ', $cols).')');
        $ins = $db->prepare("INSERT INTO gpkg_contents (table_name, data_type, identifier, description, srs_id) VALUES (?, 'features', ?, '', 4326)");
        $ins->bindValue(1, $this->table);
        $ins->bindValue(2, $layer);
        $ins->execute();
        $db->exec("INSERT INTO gpkg_geometry_columns VALUES ('{$this->table}', 'geom', '{$this->geomType}', 4326, 0, 0)");

        $names = array_map(fn ($f) => '"'.$f->name.'"', $fields);
        $this->insert = $db->prepare('INSERT INTO "'.$this->table.'" (geom'.($names ? ', '.implode(', ', $names) : '').') VALUES (?'.str_repeat(', ?', count($names)).')');
        $db->exec('BEGIN');
    }

    public function write(array $props, ?array $geom): void
    {
        $blob = null;
        if ($geom) {
            $geom = $geom['type'] === 'Point' ? $geom : Geometry::orient(Geometry::toMulti($geom), outerClockwise: false);
            $bb = Geometry::bbox($geom);
            $this->bbox = Geometry::mergeBbox($this->bbox, $bb);
            if ($geom['type'] === 'Point') {
                $header = 'GP'.pack('CC', 0, 0x01).pack('l', 4326); // sem envelope, little-endian
            } else {
                $header = 'GP'.pack('CC', 0, 0x03).pack('l', 4326).pack('eeee', $bb[0], $bb[2], $bb[1], $bb[3]);
            }
            $blob = $header.Wkb::encode($geom);
        }
        $this->insert->bindValue(1, $blob, $blob === null ? SQLITE3_NULL : SQLITE3_BLOB);
        foreach ($this->fields as $i => $f) {
            $v = $props[$f->name] ?? null;
            $type = $v === null ? SQLITE3_NULL : match ($f->type) { 'int' => SQLITE3_INTEGER, 'float' => SQLITE3_FLOAT, default => SQLITE3_TEXT };
            $this->insert->bindValue($i + 2, $v, $type);
        }
        $this->insert->execute();
        $this->insert->reset();
    }

    public function close(): array
    {
        $this->db->exec('COMMIT');
        if ($this->bbox) {
            $s = $this->db->prepare('UPDATE gpkg_contents SET min_x=?, min_y=?, max_x=?, max_y=? WHERE table_name=?');
            foreach ([...$this->bbox, $this->table] as $i => $v) {
                $s->bindValue($i + 1, $v);
            }
            $s->execute();
        }
        $this->db->exec('VACUUM');
        $this->db->close();

        return [$this->path];
    }
}
