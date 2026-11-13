<?php

namespace App\Support;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/** Leitor mínimo de XLSX (só valores): devolve cada folha como lista de linhas associativas pelo cabeçalho. */
class Xlsx
{
    /** @return array<string, list<array<string, string>>> */
    public static function read(string $file): array
    {
        $z = new ZipArchive();
        if ($z->open($file) !== true) {
            throw new RuntimeException("Não foi possível abrir {$file}");
        }

        $strings = [];
        if (($x = $z->getFromName('xl/sharedStrings.xml')) !== false) {
            foreach (simplexml_load_string($x)->si as $si) {
                $t = isset($si->t) ? (string) $si->t : '';
                foreach ($si->r as $r) {
                    $t .= (string) $r->t;
                }
                $strings[] = $t;
            }
        }

        $rels = [];
        foreach (simplexml_load_string($z->getFromName('xl/_rels/workbook.xml.rels'))->Relationship as $r) {
            $rels[(string) $r['Id']] = ltrim(str_replace('/xl/', '', (string) $r['Target']), '/');
        }

        $out = [];
        foreach (simplexml_load_string($z->getFromName('xl/workbook.xml'))->sheets->sheet as $s) {
            $rid = (string) $s->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $xml = $z->getFromName('xl/'.$rels[$rid]);
            $out[(string) $s['name']] = $xml === false ? [] : self::rows(simplexml_load_string($xml), $strings);
        }
        $z->close();

        return $out;
    }

    /** @return list<array<string, string>> */
    protected static function rows(SimpleXMLElement $sheet, array $strings): array
    {
        $header = null;
        $rows = [];
        foreach ($sheet->sheetData->row as $row) {
            $vals = [];
            foreach ($row->c as $c) {
                $col = preg_replace('/\d+/', '', (string) $c['r']);
                $v = (string) $c->v;
                $type = (string) $c['t'];
                if ($type === 's') {
                    $v = $strings[(int) $v] ?? '';
                } elseif ($type === 'inlineStr') {
                    $v = (string) $c->is->t;
                }
                $vals[$col] = $v;
            }
            if ($header === null) {
                if (array_filter($vals, fn ($v) => $v !== '')) {
                    $header = $vals;
                }

                continue;
            }
            $assoc = [];
            foreach ($header as $col => $name) {
                $assoc[$name] = $vals[$col] ?? '';
            }
            $rows[] = $assoc;
        }

        return $rows;
    }
}
