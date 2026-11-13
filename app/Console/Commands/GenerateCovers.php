<?php

namespace App\Console\Commands;

use App\Models\Study;
use App\Models\Tutorial;
use App\Support\Charts\Figures;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickPixel;
use ZipArchive;

/**
 * Capas originais (1600×900, WebP) para tutoriais e pesquisas, desenhadas a partir de dados geográficos reais
 * de Moçambique (limites, focos VIIRS, perda de cobertura arbórea do GFW). Sem texto: servem nos três idiomas.
 */
class GenerateCovers extends Command
{
    protected $signature = 'geofloresta:covers {--only= : ex. tutorial-1,study-2} {--dry : só gera os ficheiros, não altera a capa}';

    protected $description = 'Gera as imagens de destaque originais dos tutoriais e pesquisas';

    protected const W = 1600;
    protected const H = 900;
    protected const BBOX = [30.2, -26.9, 40.9, -10.4]; // lon/lat de Moçambique

    protected array $provinces = [];
    protected array $districts = [];
    protected float $k;
    protected float $x0;
    protected float $y0;

    public function handle(): int
    {
        $pub = Storage::disk('public');
        $this->provinces = json_decode($pub->get('geo/mz-provincias.geojson'), true)['features'] ?? [];
        $this->districts = json_decode($pub->get('geo/mz-distritos.geojson'), true)['features'] ?? [];
        $this->place(1015, 40, 820);

        $only = array_filter(explode(',', (string) $this->option('only')));
        foreach ($this->specs() as $key => [$model, $alt, $draw]) {
            if ($only && ! in_array($key, $only, true)) {
                continue;
            }
            if (! $model) {
                $this->warn("{$key}: registo não encontrado");

                continue;
            }
            $this->place(1015, 40, 820);
            $svg = $this->frame($draw());
            $path = "capas/{$key}.webp";
            $img = new Imagick();
            $img->setBackgroundColor(new ImagickPixel('#0B1912'));
            $img->readImageBlob($svg);
            $img->setImageFormat('webp');
            $img->setImageCompressionQuality(84);
            $img->stripImage();
            $pub->put($path, $img->getImageBlob());
            $this->line(sprintf('%-12s %s (%s KB)', $key, $path, round($pub->size($path) / 1024)));
            if (! $this->option('dry')) {
                $model->forceFill(['cover_image' => $path, 'cover_alt' => $alt])->saveQuietly();
            }
        }

        return self::SUCCESS;
    }

    /** chave => [modelo, texto alternativo, função que devolve o SVG interior] */
    protected function specs(): array
    {
        $t = fn ($id) => Tutorial::find($id);
        $s = fn ($id) => Study::find($id);

        return [
            'tutorial-1' => [$t(1), 'Mapa de Moçambique com os focos de incêndio detectados pelo satélite VIIRS em 2025, a laranja sobre fundo escuro.', fn () => $this->fires()],
            'tutorial-2' => [$t(2), 'Ilustração: faixas de cor das bandas espectrais (azul, verde, vermelho e infravermelho) atravessam o mapa de Moçambique.', fn () => $this->bands()],
            'tutorial-3' => [$t(3), 'Ilustração: órbitas e faixa de imagem de um satélite Landsat sobre o mapa de Moçambique.', fn () => $this->orbits(1)],
            'tutorial-4' => [$t(4), 'Ilustração: linhas de temperatura em tons quentes, de amarelo a vermelho, à volta do mapa de Moçambique.', fn () => $this->heat()],
            'tutorial-5' => [$t(5), 'Ilustração: ondas oceânicas quentes e frias junto à costa de Moçambique, evocando o El Niño.', fn () => $this->waves()],
            'tutorial-6' => [$t(6), 'Mapa de Moçambique com a província de Sofala realçada a azul e linhas de água estilizadas.', fn () => $this->sofalaWater()],
            'tutorial-7' => [$t(7), 'Ilustração: grelha de pontos quentes arrefecida por copas de árvores verdes, sobre o mapa de Moçambique.', fn () => $this->urbanHeat()],
            'tutorial-8' => [$t(8), 'Ilustração: mosaico de píxeis multiespectrais que se desfaz no mapa de Moçambique.', fn () => $this->pixels()],
            'tutorial-9' => [$t(9), 'Ilustração: vários satélites em órbita e ondas de radar sobre o mapa de Moçambique.', fn () => $this->orbits(3)],
            'study-1' => [$s(1), 'Mapa de Moçambique com as províncias coloridas pela perda de cobertura arbórea de 2001 a 2025 e anel com a parte de cada vetor de desflorestamento.', fn () => $this->drivers()],
            'study-2' => [$s(2), 'Vinte e cinco faixas verticais, uma por ano de 2001 a 2025, coloridas pela perda anual de cobertura arbórea em Moçambique, ao lado do mapa do país.', fn () => $this->stripes()],
            'study-3' => [$s(3), 'Mapa da província de Sofala com a cidade da Beira assinalada e ondas azuis a partir da costa, evocando as inundações.', fn () => $this->beira()],
            'study-4' => [$s(4), 'Mapa de Moçambique com a província do Niassa realçada a verde-escuro, textura de copas e perfil vertical de altura da vegetação.', fn () => $this->niassa()],
        ];
    }

    /* ---------- base ---------- */

    protected function place(float $x, float $y, float $h): void
    {
        [$lon0, $lat0, $lon1, $lat1] = self::BBOX;
        $this->k = $h / ($lat1 - $lat0);
        $this->x0 = $x;
        $this->y0 = $y;
    }

    protected function xy(float $lon, float $lat): array
    {
        [$lon0, , , $lat1] = self::BBOX;

        return [round($this->x0 + ($lon - $lon0) * $this->k * 0.95, 1), round($this->y0 + ($lat1 - $lat) * $this->k, 1)];
    }

    protected function path(array $geom, int $step = 1): string
    {
        $polys = $geom['type'] === 'Polygon' ? [$geom['coordinates']] : ($geom['type'] === 'MultiPolygon' ? $geom['coordinates'] : []);
        $d = '';
        foreach ($polys as $poly) {
            foreach ($poly as $ring) {
                if (count($ring) < 4) {
                    continue;
                }
                $pts = [];
                foreach ($ring as $i => $c) {
                    if ($i % $step === 0 || $i === count($ring) - 1) {
                        $pts[] = implode(',', $this->xy($c[0], $c[1]));
                    }
                }
                $d .= 'M'.implode('L', $pts).'Z';
            }
        }

        return $d;
    }

    protected function provinceName(array $f): string
    {
        $p = $f['properties'] ?? [];

        return (string) ($p['nome'] ?? $p['name'] ?? $p['shapeName'] ?? $p['NAME_1'] ?? '');
    }

    protected function country(string $fill = 'none', string $stroke = '#E9E6DA', float $sw = 1.6, float $op = .9): string
    {
        $o = '';
        foreach ($this->provinces as $f) {
            $o .= '<path d="'.$this->path($f['geometry']).'" fill="'.$fill.'" stroke="'.$stroke.'" stroke-width="'.$sw.'" stroke-opacity="'.$op.'" stroke-linejoin="round"/>';
        }

        return $o;
    }

    protected function districtLines(string $stroke = '#7FB08A', float $op = .18): string
    {
        $o = '';
        foreach ($this->districts as $f) {
            $o .= '<path d="'.$this->path($f['geometry'], 3).'" fill="none" stroke="'.$stroke.'" stroke-width=".7" stroke-opacity="'.$op.'"/>';
        }

        return $o;
    }

    protected function frame(string $inner, string $from = '#0B1912', string $to = '#163322'): string
    {
        $g = '';
        for ($lon = 20; $lon <= 50; $lon += 2) {
            [$x] = $this->xy($lon, -20);
            $g .= '<line x1="'.$x.'" y1="0" x2="'.$x.'" y2="'.self::H.'"/>';
        }
        for ($lat = -40; $lat <= 0; $lat += 2) {
            [, $y] = $this->xy(35, $lat);
            $g .= '<line x1="0" y1="'.$y.'" x2="'.self::W.'" y2="'.$y.'"/>';
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.self::W.'" height="'.self::H.'" viewBox="0 0 '.self::W.' '.self::H.'">'
            .'<defs><linearGradient id="bg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="'.$from.'"/><stop offset="1" stop-color="'.$to.'"/></linearGradient>'
            .'<radialGradient id="glow" cx=".5" cy=".5" r=".5"><stop offset="0" stop-color="#F06A33" stop-opacity=".55"/><stop offset="1" stop-color="#F06A33" stop-opacity="0"/></radialGradient></defs>'
            .'<rect width="'.self::W.'" height="'.self::H.'" fill="url(#bg)"/>'
            .'<g stroke="#E9E6DA" stroke-opacity=".06" stroke-width="1">'.$g.'</g>'
            .$inner
            .'</svg>';
    }

    protected function contours(float $cx, float $cy, int $n, float $r0, float $dr, string $color, float $op, float $wob = 0.0): string
    {
        $o = '';
        for ($i = 0; $i < $n; $i++) {
            $r = $r0 + $i * $dr;
            $pts = [];
            for ($a = 0; $a <= 360; $a += 6) {
                $rad = deg2rad($a);
                $rr = $r * (1 + $wob * sin($rad * 3 + $i * .7) + $wob * .5 * cos($rad * 5 - $i));
                $pts[] = round($cx + cos($rad) * $rr, 1).','.round($cy + sin($rad) * $rr * .82, 1);
            }
            $o .= '<polyline points="'.implode(' ', $pts).'" fill="none" stroke="'.$color.'" stroke-opacity="'.round($op * (1 - $i / ($n + 2)), 3).'" stroke-width="1.4"/>';
        }

        return $o;
    }

    /* ---------- tutoriais ---------- */

    protected function fires(): string
    {
        $pts = $this->firePoints(14);
        $o = '<circle cx="1290" cy="420" r="520" fill="url(#glow)" opacity=".35"/>'.$this->districtLines('#E9E6DA', .1).$this->country('none', '#E9E6DA', 1.2, .55);
        $o .= '<g fill="#F06A33" fill-opacity=".42">';
        foreach ($pts as [$lon, $lat]) {
            [$x, $y] = $this->xy($lon, $lat);
            $o .= '<circle cx="'.$x.'" cy="'.$y.'" r="1.5"/>';
        }
        $o .= '</g>'.$this->contours(330, 520, 9, 60, 34, '#F06A33', .5, .06);

        return $o;
    }

    protected function bands(): string
    {
        $o = '';
        foreach ([['#4A7BD0', 0], ['#5DAA5F', 1], ['#D0573C', 2], ['#9B5BC8', 3]] as [$c, $i]) {
            $y = 150 + $i * 150;
            $o .= '<polygon points="-50,'.($y + 160).' 1650,'.($y - 220).' 1650,'.($y - 130).' -50,'.($y + 250).'" fill="'.$c.'" fill-opacity=".28"/>';
        }

        return $o.$this->country('#0B1912', '#E9E6DA', 1.6, .9).$this->districtLines('#7FB08A', .25);
    }

    protected function orbits(int $n): string
    {
        $o = $this->country('#12251B', '#E9E6DA', 1.4, .8).$this->districtLines('#7FB08A', .15);
        for ($i = 0; $i < $n; $i++) {
            $rot = -28 + $i * 34;
            $rx = 620 - $i * 70;
            $o .= '<g transform="rotate('.$rot.' 640 450)"><ellipse cx="640" cy="450" rx="'.$rx.'" ry="'.(230 - $i * 35).'" fill="none" stroke="#E9E6DA" stroke-opacity=".6" stroke-width="2" stroke-dasharray="3 9"/>'
                .'<circle cx="'.(640 - $rx).'" cy="450" r="12" fill="#F06A33" stroke="#FBF3E0" stroke-width="3"/></g>';
        }
        // faixa de imagem
        $o .= '<polygon points="1080,0 1260,0 1420,900 1240,900" fill="#7FB08A" fill-opacity=".14" stroke="#7FB08A" stroke-opacity=".5"/>';
        if ($n > 1) {
            for ($i = 1; $i <= 7; $i++) {
                $o .= '<circle cx="330" cy="470" r="'.(40 * $i).'" fill="none" stroke="#7FB08A" stroke-opacity="'.round(.75 - $i * .09, 2).'" stroke-width="2.2" stroke-dasharray="'.(20 + $i * 6).' '.(14 + $i * 4).'"/>';
            }
        }

        return $o;
    }

    protected function heat(): string
    {
        $o = '';
        $cols = ['#FCE38A', '#F9C25C', '#F49B45', '#EC7433', '#D9541E', '#B4441A', '#8A2F12'];
        foreach ($cols as $i => $c) {
            $o .= $this->contours(1270, 430, 1, 120 + $i * 85, 0, $c, .9, .09);
        }

        return $o.$this->country('#0B1912', '#FBF3E0', 1.6, .9);
    }

    protected function waves(): string
    {
        $o = '';
        for ($i = 0; $i < 18; $i++) {
            $y = 60 + $i * 46;
            $warm = $i < 9;
            $c = $warm ? ['#F9C25C', '#F49B45', '#EC7433', '#D9541E'][$i % 4] : ['#4FA3C7', '#3D86B0', '#2E6A93', '#7FC2DE'][$i % 4];
            $d = 'M-20,'.$y;
            for ($x = 0; $x <= 1640; $x += 40) {
                $d .= ' L'.$x.','.round($y + sin($x / 120 + $i * .6) * (14 + ($i % 5) * 3), 1);
            }
            $o .= '<path d="'.$d.'" fill="none" stroke="'.$c.'" stroke-opacity=".5" stroke-width="2.2"/>';
        }

        return $o.$this->country('#0B1912', '#E9E6DA', 1.6, .95);
    }

    protected function sofalaWater(): string
    {
        $o = '';
        for ($i = 0; $i < 9; $i++) {
            $d = 'M-20,'.(120 + $i * 80);
            for ($x = 0; $x <= 940; $x += 30) {
                $d .= ' L'.$x.','.round(120 + $i * 80 + sin($x / (60 + $i * 9) + $i) * 26 + cos($x / 140) * 18, 1);
            }
            $o .= '<path d="'.$d.'" fill="none" stroke="#4FA3C7" stroke-opacity="'.(0.55 - $i * .04).'" stroke-width="'.(3 - $i * .2).'" stroke-linecap="round"/>';
        }
        foreach ($this->provinces as $f) {
            $isSofala = stripos($this->provinceName($f), 'sofala') !== false;
            $o .= '<path d="'.$this->path($f['geometry']).'" fill="'.($isSofala ? '#2E6A93' : '#12251B').'" fill-opacity="'.($isSofala ? '.85' : '1').'" stroke="#E9E6DA" stroke-width="1.3" stroke-opacity=".8"/>';
        }

        return $o.$this->districtLines('#7FC2DE', .2);
    }

    protected function urbanHeat(): string
    {
        $o = '';
        mt_srand(7);
        for ($y = 70; $y < 860; $y += 34) {
            for ($x = 50; $x < 960; $x += 34) {
                $d = hypot($x - 470, $y - 450) / 520;
                $o .= '<circle cx="'.$x.'" cy="'.$y.'" r="'.round(3 + (1 - min($d, 1)) * 6, 1).'" fill="'.($d < .45 ? '#EC7433' : ($d < .75 ? '#F9C25C' : '#7FB08A')).'" fill-opacity=".7"/>';
            }
        }
        for ($i = 0; $i < 22; $i++) {
            $o .= '<circle cx="'.mt_rand(80, 900).'" cy="'.mt_rand(90, 820).'" r="'.mt_rand(26, 52).'" fill="#2A8A4A" fill-opacity=".55" stroke="#7FB08A" stroke-opacity=".7"/>';
        }

        return $o.$this->country('#0B1912', '#E9E6DA', 1.5, .85);
    }

    protected function pixels(): string
    {
        $o = '';
        mt_srand(11);
        $pal = ['#1F4A2E', '#3F7A50', '#7FAE86', '#C8B26A', '#9C6B3E', '#4A7BD0', '#2E6A93'];
        for ($y = 0; $y < 900; $y += 40) {
            for ($x = 0; $x < 1000; $x += 40) {
                $fade = 1 - $x / 1000;
                if (mt_rand(0, 100) / 100 < $fade + .15) {
                    $o .= '<rect x="'.$x.'" y="'.$y.'" width="38" height="38" fill="'.$pal[mt_rand(0, count($pal) - 1)].'" fill-opacity="'.round(.25 + $fade * .55, 2).'"/>';
                }
            }
        }

        return $o.$this->country('#0B1912', '#E9E6DA', 1.6, .9).$this->districtLines('#7FB08A', .22);
    }

    /* ---------- pesquisas (dados reais) ---------- */

    protected function drivers(): string
    {
        $d = Figures::data();
        $loss = $d['provinces_loss_30'] ?? [];
        $max = max($loss ?: [1]);
        $ramp = ['#FCE3C4', '#F6B67E', '#EC8445', '#C8601A', '#8A3A12'];
        $o = '';
        foreach ($this->provinces as $f) {
            $name = $this->provinceName($f);
            $v = 0;
            foreach ($loss as $p => $val) {
                if ($this->same($p, $name)) {
                    $v = $val;
                }
            }
            $c = $ramp[min(4, (int) floor($v / $max * 4.999))];
            $o .= '<path d="'.$this->path($f['geometry']).'" fill="'.$c.'" stroke="#0B1912" stroke-width="2"/>';
        }
        // anel: parte de cada vetor
        $tot = array_map('array_sum', $d['drivers'] ?? []);
        arsort($tot);
        $sum = array_sum($tot) ?: 1;
        $a = -90;
        $cols = ['#C8601A', '#F6B67E', '#7FB08A', '#3F7A50', '#E9E6DA', '#4FA3C7', '#9C6B3E'];
        $i = 0;
        foreach ($tot as $v) {
            $sweep = $v / $sum * 360;
            $o .= $this->arc(450, 450, 270, 200, $a, $a + max($sweep - .6, .3), $cols[$i++ % 7]);
            $a += $sweep;
        }

        return $o;
    }

    protected function arc(float $cx, float $cy, float $r, float $r2, float $a0, float $a1, string $c): string
    {
        $p = fn ($a, $r) => round($cx + cos(deg2rad($a)) * $r, 1).','.round($cy + sin(deg2rad($a)) * $r, 1);
        $large = ($a1 - $a0) > 180 ? 1 : 0;

        return '<path d="M'.$p($a0, $r).' A'.$r.','.$r.' 0 '.$large.' 1 '.$p($a1, $r).' L'.$p($a1, $r2).' A'.$r2.','.$r2.' 0 '.$large.' 0 '.$p($a0, $r2).'Z" fill="'.$c.'"/>';
    }

    protected function stripes(): string
    {
        $d = Figures::data();
        $vals = $d['loss'][30]['annual'] ?? array_fill(0, 25, 0);
        $min = min($vals);
        $max = max($vals) ?: 1;
        $ramp = ['#FCE3C4', '#F6B67E', '#EC8445', '#D9541E', '#B4441A', '#8A2F12'];
        $o = '';
        $w = 900 / count($vals);
        foreach ($vals as $i => $v) {
            $t = ($v - $min) / max($max - $min, 1);
            $o .= '<rect x="'.round($i * $w, 1).'" y="0" width="'.round($w + .5, 1).'" height="900" fill="'.$ramp[min(5, (int) floor($t * 5.999))].'"/>';
        }

        return $o.$this->country('#12251B', '#E9E6DA', 1.4, .9).$this->districtLines('#F6B67E', .18);
    }

    protected function beira(): string
    {
        // Sofala em grande
        $this->place(700, -260, 1600);
        $o = '';
        foreach ($this->provinces as $f) {
            $isSofala = stripos($this->provinceName($f), 'sofala') !== false;
            $o .= '<path d="'.$this->path($f['geometry']).'" fill="'.($isSofala ? '#183024' : '#0F2018').'" stroke="#E9E6DA" stroke-width="'.($isSofala ? 2 : 1).'" stroke-opacity="'.($isSofala ? .9 : .3).'"/>';
        }
        $o .= $this->districtLines('#7FC2DE', .28);
        [$bx, $by] = $this->xy(34.85, -19.83);
        for ($i = 1; $i <= 10; $i++) {
            $o .= '<circle cx="'.$bx.'" cy="'.$by.'" r="'.($i * 38).'" fill="none" stroke="#4FA3C7" stroke-opacity="'.round(.7 - $i * .06, 2).'" stroke-width="2"/>';
        }

        return '<rect x="0" y="0" width="1600" height="900" fill="#0E2A3A" opacity=".55"/>'.$o.'<circle cx="'.$bx.'" cy="'.$by.'" r="11" fill="#F06A33" stroke="#FBF3E0" stroke-width="3"/>';
    }

    protected function niassa(): string
    {
        $o = '';
        mt_srand(3);
        foreach ($this->provinces as $f) {
            $is = stripos($this->provinceName($f), 'niassa') !== false;
            $o .= '<path d="'.$this->path($f['geometry']).'" fill="'.($is ? '#1F4A2E' : '#12251B').'" stroke="#E9E6DA" stroke-width="'.($is ? 2 : 1.2).'" stroke-opacity="'.($is ? .95 : .5).'"/>';
        }
        // perfil vertical (LiDAR) à esquerda
        for ($i = 0; $i < 70; $i++) {
            $h = 120 + abs(sin($i / 6.5)) * 260 + mt_rand(0, 90);
            $o .= '<rect x="'.(70 + $i * 12).'" y="'.(820 - $h).'" width="7" height="'.$h.'" rx="2" fill="#3FA35F" fill-opacity="'.round(.35 + $h / 900, 2).'"/>';
        }
        for ($i = 0; $i < 260; $i++) {
            $o .= '<circle cx="'.mt_rand(60, 930).'" cy="'.mt_rand(60, 380).'" r="'.mt_rand(4, 12).'" fill="#7FB08A" fill-opacity=".18"/>';
        }

        return $o;
    }

    /* ---------- dados ---------- */

    protected function same(string $a, string $b): bool
    {
        // nomes do GFW (inglês, com gralhas) => nomes do mapa
        $alias = ['nassa' => 'niassa', 'maputocity' => 'cidadedemaputo', 'maputoprovince' => 'maputo', 'provinciademaputo' => 'maputo'];
        $n = function ($s) use ($alias) {
            $k = preg_replace('/[^a-z]/', '', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $s)));

            return $alias[$k] ?? $k;
        };

        return $n($a) === $n($b);
    }

    /** Amostra dos focos VIIRS 2025 do conjunto publicado no catálogo. */
    protected function firePoints(int $every): array
    {
        $zip = storage_path('app/datasets/files/focos-de-incendio-mocambique-viirs-2025/mz_focos_viirs-snpp_2025_csv.zip');
        $pts = [];
        $z = new ZipArchive();
        if (! is_file($zip) || $z->open($zip) !== true) {
            return $pts;
        }
        for ($i = 0; $i < $z->numFiles; $i++) {
            $name = $z->getNameIndex($i);
            if (! str_ends_with(strtolower($name), '.csv') || str_contains($name, 'resumo')) {
                continue;
            }
            $fh = $z->getStream($name);
            $head = array_map('strtolower', fgetcsv($fh));
            $la = array_search('latitude', $head, true);
            $lo = array_search('longitude', $head, true);
            $n = 0;
            while (($r = fgetcsv($fh)) !== false) {
                if ($n++ % $every === 0 && isset($r[$la], $r[$lo])) {
                    $pts[] = [(float) $r[$lo], (float) $r[$la]];
                }
            }
            fclose($fh);
            break;
        }
        $z->close();

        return $pts;
    }
}
