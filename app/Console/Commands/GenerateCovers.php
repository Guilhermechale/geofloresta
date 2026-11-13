<?php

namespace App\Console\Commands;

use App\Models\Study;
use App\Models\Tutorial;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickDraw;
use ImagickPixel;
use RuntimeException;

/**
 * Capas (1600×900, WebP) com imagens de satélite reais e abertas, escolhidas pelo tema de cada tutorial/pesquisa:
 *  - Sentinel-2 cloudless 2016 (EOX, CC BY 4.0), 10 m, sem nuvens;
 *  - NASA GIBS (MODIS/VIIRS, SST, MERRA-2; domínio público), imagens do dia ou mensais;
 *  - perda de cobertura arbórea Hansen/UMD/Google/USGS/NASA (CC BY 4.0).
 * Sem texto sobre a imagem: servem nos três idiomas. O crédito fica em cover_credit.
 */
class GenerateCovers extends Command
{
    protected $signature = 'geofloresta:covers {--only= : ex. tutorial-1,study-2} {--dry : grava em capas-preview/ e não altera os registos}';

    protected $description = 'Gera as imagens de destaque (imagens de satélite reais) dos tutoriais e pesquisas';

    protected const W = 1600;
    protected const H = 900;

    protected const EOX = 'https://tiles.maps.eox.at/wmts/1.0.0/s2cloudless_3857/default/g/{z}/{y}/{x}.jpg';
    protected const HANSEN = 'https://storage.googleapis.com/earthenginepartners-hansen/tiles/gfc_v1.12/loss_alpha/{z}/{x}/{y}.png';
    protected const GIBS = 'https://gibs.earthdata.nasa.gov/wms/epsg3857/best/wms.cgi';

    protected const C_S2 = 'Sentinel-2 cloudless 2016 by EOX IT Services GmbH (contains modified Copernicus Sentinel data 2016), CC BY 4.0';
    protected const C_NASA = 'NASA Worldview / GIBS (EOSDIS)';
    protected const C_HANSEN = 'Hansen/UMD/Google/USGS/NASA tree cover loss, CC BY 4.0';

    public function handle(): int
    {
        $pub = Storage::disk('public');
        $only = array_filter(explode(',', (string) $this->option('only')));
        $dir = $this->option('dry') ? 'capas-preview' : 'capas';

        foreach ($this->specs() as $key => $spec) {
            if ($only && ! in_array($key, $only, true)) {
                continue;
            }
            [$model, $alt, $credit, $make] = $spec;
            if (! $model) {
                $this->warn("{$key}: registo não encontrado");

                continue;
            }
            try {
                $img = $make();
            } catch (\Throwable $e) {
                $this->error("{$key}: {$e->getMessage()}");

                continue;
            }
            $img->setImageFormat('webp');
            $img->setImageCompressionQuality(84);
            $img->stripImage();
            $path = "{$dir}/{$key}.webp";
            $pub->put($path, $img->getImageBlob());
            $this->line(sprintf('%-11s %s (%d KB)', $key, $path, round($pub->size($path) / 1024)));
            if (! $this->option('dry')) {
                $model->forceFill(['cover_image' => $path, 'cover_alt' => $alt, 'cover_credit' => $credit])->saveQuietly();
            }
        }

        return self::SUCCESS;
    }

    /** chave => [registo, texto alternativo, crédito, função que devolve a imagem] */
    protected function specs(): array
    {
        $t = fn ($id) => Tutorial::find($id);
        $s = fn ($id) => Study::find($id);
        $nasa = self::C_NASA;

        return [
            'tutorial-1' => [$t(1),
                'Imagem de satélite real do centro de Moçambique na época seca (8 de Setembro de 2025), com fumo das queimadas e os focos de incêndio detectados pelo VIIRS a vermelho.',
                "{$nasa}: MODIS Terra true colour and VIIRS fire detections (NOAA-20, Suomi NPP), 2025-09-08",
                fn () => $this->overlay(
                    $this->gibs('MODIS_Terra_CorrectedReflectance_TrueColor', '2025-09-08', 34.2, -17.6, 450),
                    $this->gibs('VIIRS_SNPP_Thermal_Anomalies_375m_All', '2025-09-08', 34.2, -17.6, 450, 'image/png', null, self::W, 3, Imagick::FILTER_POINT),
                    $this->gibs('VIIRS_NOAA20_Thermal_Anomalies_375m_All', '2025-09-08', 34.2, -17.6, 450, 'image/png', null, self::W, 3, Imagick::FILTER_POINT),
                )],
            'tutorial-2' => [$t(2),
                'A mesma região de Sofala em duas versões: à esquerda, imagem de satélite em cor natural; à direita, o índice de vegetação NDVI em tons de verde.',
                self::C_S2.' · '.$nasa.': MODIS NDVI 8-day',
                fn () => $this->split(
                    $this->tiles(self::EOX, 34.4, -18.9, 200, 14),
                    $this->gibs('MODIS_Terra_NDVI_8Day', '2025-03-06', 34.4, -18.9, 200, 'image/png'),
                )],
            'tutorial-3' => [$t(3),
                'Imagem de satélite real do delta do rio Zambeze, em Moçambique, com os canais do rio, mangais e a costa do oceano Índico.',
                self::C_S2,
                fn () => $this->tiles(self::EOX, 36.15, -18.6, 110, 14)],
            'tutorial-4' => [$t(4),
                'Mapa da temperatura do ar junto ao solo sobre África, em tons de amarelo, laranja e vermelho, com o contorno dos países e da costa.',
                "{$nasa}: MERRA-2 2 m air temperature, October 2024",
                fn () => $this->overlay(
                    $this->gibs('MERRA2_2m_Air_Temperature_Monthly', '2024-10-01', 18, -5, 8200, 'image/png', '#FFFFFF', self::W, 8),
                    $this->gibs('Reference_Features_15m', '2024-10-01', 18, -5, 8200, 'image/png'),
                    $this->gibs('Coastlines_15m', '2024-10-01', 18, -5, 8200, 'image/png'),
                )],
            'tutorial-5' => [$t(5),
                'Mapa real da anomalia da temperatura da superfície do mar no oceano Pacífico em Dezembro de 2023, com a língua de água quente do El Niño a vermelho ao longo do equador.',
                "{$nasa}: GHRSST MUR sea surface temperature anomaly, 2023-12-15",
                fn () => $this->overlay(
                    $this->gibs('GHRSST_L4_MUR_Sea_Surface_Temperature_Anomalies', '2023-12-15', -120, 0, 11000, 'image/png', '#FFFFFF'),
                    $this->gibs('Coastlines_15m', '2023-12-15', -120, 0, 11000, 'image/png'),
                )],
            'tutorial-6' => [$t(6),
                'Imagem de satélite real do rio Zambeze perto de Caia, em Moçambique, com os seus braços, ilhas de areia e a vegetação das margens.',
                self::C_S2,
                fn () => $this->tiles(self::EOX, 35.38, -17.82, 45, 15)],
            'tutorial-7' => [$t(7),
                'Imagem de satélite real da cidade da Beira, em Moçambique: o tecido urbano junto ao mar contrasta com as zonas verdes e arborizadas à volta.',
                self::C_S2,
                fn () => $this->tiles(self::EOX, 34.87, -19.81, 16, 16)],
            'tutorial-8' => [$t(8),
                'A mesma paisagem do centro de Moçambique em cor natural (à esquerda) e em falsa cor multiespectral, bandas 7-2-1 (à direita), onde a vegetação surge verde vivo e as áreas queimadas castanho-avermelhado.',
                self::C_S2.' · '.$nasa.': MODIS Terra bands 7-2-1, 2025-09-08',
                fn () => $this->split(
                    $this->tiles(self::EOX, 34.3, -17.9, 320, 13),
                    $this->gibs('MODIS_Terra_CorrectedReflectance_Bands721', '2025-09-08', 34.3, -17.9, 320),
                )],
            'tutorial-9' => [$t(9),
                'Três imagens de satélite da região da Beira com resoluções diferentes, da mais larga à mais detalhada: a região centro de Moçambique, o estuário do Púnguè e a cidade.',
                "{$nasa}: MODIS Terra true colour, 2025-09-08 · ".self::C_S2,
                fn () => $this->panels([
                    $this->gibs('MODIS_Terra_CorrectedReflectance_TrueColor', '2025-09-08', 34.9, -19.3, 300, 'image/jpeg', null, 533),
                    $this->tiles(self::EOX, 34.85, -19.75, 45, 15, 533),
                    $this->tiles(self::EOX, 34.86, -19.835, 6, 17, 534),
                ])],
            'study-1' => [$s(1),
                'Imagem de satélite real de uma paisagem agrícola e florestal na Zambézia, em Moçambique, com as áreas de perda de cobertura arbórea de 2001 a 2024 assinaladas a vermelho.',
                self::C_S2.' · '.self::C_HANSEN,
                fn () => $this->overlay($this->tiles(self::EOX, 37.0, -16.0, 70, 14), $this->tiles(self::HANSEN, 37.0, -16.0, 70, 12))],
            'study-2' => [$s(2),
                'Imagem de satélite real do norte de Moçambique com toda a perda de cobertura arbórea de 2001 a 2024 assinalada a vermelho sobre a floresta de miombo.',
                self::C_S2.' · '.self::C_HANSEN,
                fn () => $this->overlay($this->tiles(self::EOX, 38.6, -14.6, 160, 13), $this->tiles(self::HANSEN, 38.6, -14.6, 160, 12))],
            'study-3' => [$s(3),
                'Imagem de satélite real de 26 de Março de 2019, depois do ciclone Idai: as águas castanhas das cheias dos rios Búzi e Púnguè cobrem as planícies junto à cidade da Beira.',
                "{$nasa}: MODIS Terra true colour, 2019-03-26",
                fn () => $this->gibs('MODIS_Terra_CorrectedReflectance_TrueColor', '2019-03-26', 34.5, -19.6, 200)],
            'study-4' => [$s(4),
                'Imagem de satélite real da Reserva Especial do Niassa, em Moçambique: floresta de miombo, montes de granito e o rio Lugenda.',
                self::C_S2,
                fn () => $this->tiles(self::EOX, 37.8, -12.1, 90, 14)],
        ];
    }

    /* ---------- fontes ---------- */

    /** Extensão em Mercator (EPSG:3857) para um centro e uma largura no terreno (km). */
    protected function bbox(float $lon, float $lat, float $km, int $w, int $h): array
    {
        $x = $lon * 20037508.34 / 180;
        $y = log(tan((90 + $lat) * M_PI / 360)) / (M_PI / 180) * 20037508.34 / 180;
        $half = $km * 500 / cos(deg2rad($lat));

        return [$x - $half, $y - $half * $h / $w, $x + $half, $y + $half * $h / $w];
    }

    protected function fetch(string $url): string
    {
        // Repete só em erros temporários; um 404 (ex.: mosaico sem perda florestal) não se repete
        $r = Http::timeout(90)->retry(3, 1500, fn ($e) => ! ($e instanceof \Illuminate\Http\Client\RequestException && $e->response->status() < 500), throw: false)
            ->withUserAgent('GEOFLORESTA/1.0 (geofloresta.com; capas)')->get($url);
        if (! $r->successful()) {
            throw new RuntimeException("HTTP {$r->status()}: {$url}");
        }

        return $r->body();
    }

    protected function gibs(string $layer, string $time, float $lon, float $lat, float $km, string $fmt = 'image/jpeg', ?string $bg = null, int $w = self::W, int $down = 1, int $filter = Imagick::FILTER_LANCZOS): Imagick
    {
        if ($down > 1) {
            $q = http_build_query(['SERVICE' => 'WMS', 'REQUEST' => 'GetMap', 'VERSION' => '1.1.1', 'LAYERS' => $layer, 'STYLES' => '', 'SRS' => 'EPSG:3857',
                'BBOX' => implode(',', array_map(fn ($v) => round($v), $this->bbox($lon, $lat, $km, $w, self::H))),
                'WIDTH' => intdiv($w, $down), 'HEIGHT' => intdiv(self::H, $down), 'FORMAT' => $fmt, 'TRANSPARENT' => 'TRUE', 'TIME' => $time]);
            $small = new Imagick();
            $small->readImageBlob($this->fetch(self::GIBS.'?'.$q));
            $small->setImageType(Imagick::IMGTYPE_TRUECOLORALPHA);
            $small->resizeImage($w, self::H, $filter, 1);
            if ($filter === Imagick::FILTER_LANCZOS && $down >= 4) {
                $small->gaussianBlurImage(0, $down * 1.5); // dados em grelha grossa (ex.: MERRA-2): transições suaves
            }
            if ($bg) {
                $base = new Imagick();
                $base->newImage($w, self::H, new ImagickPixel($bg));
                $base->compositeImage($small, Imagick::COMPOSITE_OVER, 0, 0);
                $small = $base;
            }

            return $small;
        }
        $q = http_build_query(['SERVICE' => 'WMS', 'REQUEST' => 'GetMap', 'VERSION' => '1.1.1', 'LAYERS' => $layer, 'STYLES' => '', 'SRS' => 'EPSG:3857',
            'BBOX' => implode(',', array_map(fn ($v) => round($v), $this->bbox($lon, $lat, $km, $w, self::H))),
            'WIDTH' => $w, 'HEIGHT' => self::H, 'FORMAT' => $fmt, 'TRANSPARENT' => 'TRUE', 'TIME' => $time]);
        $img = new Imagick();
        $img->readImageBlob($this->fetch(self::GIBS.'?'.$q));
        $img->setImageType($fmt === 'image/png' ? Imagick::IMGTYPE_TRUECOLORALPHA : Imagick::IMGTYPE_TRUECOLOR);
        if ($bg) { // fundo claro por baixo de camadas transparentes
            $base = new Imagick();
            $base->newImage($w, self::H, new ImagickPixel($bg));
            $base->compositeImage($img, Imagick::COMPOSITE_OVER, 0, 0);
            $img = $base;
        }
        if ($fmt === 'image/jpeg') {
            $this->enhance($img);
        }

        return $img;
    }

    /** Mosaico de mosaicos XYZ (Web Mercator) recortado ao centro e largura pedidos. */
    protected function tiles(string $tpl, float $lon, float $lat, float $km, int $zMax, int $w = self::W): Imagick
    {
        $h = self::H;
        $mpp = $km * 1000 / $w;
        $z = min($zMax, (int) ceil(log(156543.034 * cos(deg2rad($lat)) / $mpp, 2)));
        $n = 256 * 2 ** $z;
        $px = ($lon + 180) / 360 * $n;
        $py = (1 - asinh(tan(deg2rad($lat))) / M_PI) / 2 * $n;
        $spanX = $km * 1000 / (156543.034 * cos(deg2rad($lat)) / 2 ** $z);
        $spanY = $spanX * $h / $w;
        [$x0, $y0] = [$px - $spanX / 2, $py - $spanY / 2];
        [$tx0, $ty0, $tx1, $ty1] = [(int) floor($x0 / 256), (int) floor($y0 / 256), (int) floor(($x0 + $spanX) / 256), (int) floor(($y0 + $spanY) / 256)];

        $canvas = new Imagick();
        $canvas->newImage(($tx1 - $tx0 + 1) * 256, ($ty1 - $ty0 + 1) * 256, new ImagickPixel('transparent'));
        $canvas->setImageFormat('png');
        for ($tx = $tx0; $tx <= $tx1; $tx++) {
            for ($ty = $ty0; $ty <= $ty1; $ty++) {
                try {
                    $tile = new Imagick();
                    $tile->readImageBlob($this->fetch(strtr($tpl, ['{z}' => $z, '{x}' => $tx, '{y}' => $ty])));
                    $canvas->compositeImage($tile, Imagick::COMPOSITE_OVER, ($tx - $tx0) * 256, ($ty - $ty0) * 256);
                } catch (\Throwable) {
                    // mosaico em falta (ex.: sem perda florestal): fica transparente
                }
            }
        }
        $canvas->cropImage((int) round($spanX), (int) round($spanY), (int) round($x0 - $tx0 * 256), (int) round($y0 - $ty0 * 256));
        $canvas->setImagePage(0, 0, 0, 0);
        $canvas->resizeImage($w, $h, Imagick::FILTER_LANCZOS, 1);
        if (str_contains($tpl, 'eox')) {
            $this->enhance($canvas, true);
        }

        return $canvas;
    }

    /* ---------- composição ---------- */

    /** Imagens mais claras e nítidas, sem exagerar (cor próxima da realidade). */
    protected function enhance(Imagick $img, bool $strong = false): void
    {
        $img->setImageType(Imagick::IMGTYPE_TRUECOLOR);
        $img->setImageDepth(16); // evita faixas (posterização) ao clarear zonas de cor uniforme
        $img->gammaImage($strong ? 1.45 : 1.12);
        $img->modulateImage($strong ? 112 : 106, $strong ? 118 : 110, 100);
        $img->sigmoidalContrastImage(true, 2.2, 0.42 * Imagick::getQuantum());
    }

    protected function overlay(Imagick $base, Imagick ...$layers): Imagick
    {
        foreach ($layers as $l) {
            $base->compositeImage($l, Imagick::COMPOSITE_OVER, 0, 0);
        }

        return $base;
    }

    /** Duas versões da mesma área, separadas na diagonal por uma linha branca. */
    protected function split(Imagick $left, Imagick $right): Imagick
    {
        $mask = new Imagick();
        $mask->newImage(self::W, self::H, new ImagickPixel('black'));
        $d = new ImagickDraw();
        $d->setFillColor(new ImagickPixel('white'));
        $d->polygon([['x' => 0, 'y' => 0], ['x' => 900, 'y' => 0], ['x' => 700, 'y' => self::H], ['x' => 0, 'y' => self::H]]);
        $mask->drawImage($d);
        $mask->setImageAlphaChannel(Imagick::ALPHACHANNEL_OFF);
        $left->setImageAlphaChannel(Imagick::ALPHACHANNEL_SET);
        $left->compositeImage($mask, Imagick::COMPOSITE_COPYOPACITY, 0, 0);
        $out = clone $right;
        $out->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
        $out->compositeImage($left, Imagick::COMPOSITE_OVER, 0, 0);
        $line = new ImagickDraw();
        $line->setStrokeColor(new ImagickPixel('white'));
        $line->setStrokeWidth(6);
        $line->line(900, 0, 700, self::H);
        $out->drawImage($line);

        return $out;
    }

    /** Painéis lado a lado (ex.: a mesma zona em três resoluções). */
    protected function panels(array $imgs): Imagick
    {
        $out = new Imagick();
        $out->newImage(self::W, self::H, new ImagickPixel('white'));
        $x = 0;
        foreach ($imgs as $i => $img) {
            $out->compositeImage($img, Imagick::COMPOSITE_OVER, $x, 0);
            $x += $img->getImageWidth();
            if ($i < count($imgs) - 1) {
                $d = new ImagickDraw();
                $d->setFillColor(new ImagickPixel('white'));
                $d->rectangle($x - 3, 0, $x + 2, self::H);
                $out->drawImage($d);
            }
        }

        return $out;
    }
}
