<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Imagick;
use ImagickKernel;
use ImagickPixel;

/**
 * Gera, a partir de assets-originais/GF.png, as variantes do logótipo (claro/escuro)
 * e os ícones (favicon, ecrã inicial). Resultado em public/brand e public/.
 */
class BuildBrandAssets extends Command
{
    protected $signature = 'geofloresta:brand';

    protected $description = 'Gera logótipo claro/escuro e ícones a partir de assets-originais/GF.png';

    public function handle(): int
    {
        $src = base_path('assets-originais/GF.png');
        $out = public_path('brand');
        @mkdir($out, 0755, true);

        $logo = new Imagick($src);
        $logo->trimImage(0);
        $logo->setImagePage(0, 0, 0, 0);
        // Trabalhar a 512 px: chega para todos os tamanhos e a dilatação fica rápida
        $logo->resizeImage(512, 512, Imagick::FILTER_LANCZOS, 1, true);

        // Variante clara: tal como o original
        $this->writeWebp($logo, "{$out}/gf-logo.webp", 192);

        // Variante escura: contorno claro (#E9E6DA) para os círculos verde-escuros se lerem no fundo escuro
        $dark = $this->outlined($logo, '#E9E6DA', (int) round($logo->getImageWidth() * 0.025));
        $this->writeWebp($dark, "{$out}/gf-logo-dark.webp", 192);

        // Ícones: logótipo centrado num quadrado de papel
        foreach ([180 => 'apple-touch-icon.png', 192 => 'icon-192.png', 512 => 'icon-512.png'] as $size => $name) {
            $this->square($logo, $size, '#F4F1E8', 0.10)->writeImage(public_path($name === 'apple-touch-icon.png' ? $name : "brand/{$name}"));
        }
        // Maskable (zona segura de 80%)
        $this->square($logo, 512, '#F4F1E8', 0.20)->writeImage("{$out}/icon-maskable-512.png");

        // favicon.ico (16, 32, 48) com fundo transparente
        $ico = new Imagick;
        foreach ([16, 32, 48] as $s) {
            $f = $this->square($logo, $s, 'transparent', 0.0);
            $f->setImageFormat('ico');
            $ico->addImage($f);
        }
        $ico->writeImages(public_path('favicon.ico'), true);
        $this->square($logo, 32, 'transparent', 0.0)->writeImage(public_path('favicon-32.png'));

        $this->info('Logótipos e ícones gerados em public/brand e public/.');

        return self::SUCCESS;
    }

    protected function writeWebp(Imagick $img, string $path, int $size): void
    {
        $c = clone $img;
        $c->thumbnailImage($size, $size, true);
        $c->setImageFormat('webp');
        $c->setOption('webp:lossless', 'false');
        $c->setImageCompressionQuality(90);
        $c->writeImage($path);
    }

    protected function outlined(Imagick $logo, string $color, int $radius): Imagick
    {
        $pad = $radius * 2;
        $base = clone $logo;
        $base->borderImage(new ImagickPixel('transparent'), $pad, $pad);

        $outline = clone $base;
        $outline->morphology(Imagick::MORPHOLOGY_DILATE, 1, ImagickKernel::fromBuiltIn(Imagick::KERNEL_DISK, (string) $radius));
        // Pinta o contorno de uma só cor, mantendo o canal alfa
        $px = new ImagickPixel($color);
        $rgb = $px->getColor(); // 0–255
        $q = Imagick::getQuantum();
        $outline->evaluateImage(Imagick::EVALUATE_SET, $rgb['r'] / 255 * $q, Imagick::CHANNEL_RED);
        $outline->evaluateImage(Imagick::EVALUATE_SET, $rgb['g'] / 255 * $q, Imagick::CHANNEL_GREEN);
        $outline->evaluateImage(Imagick::EVALUATE_SET, $rgb['b'] / 255 * $q, Imagick::CHANNEL_BLUE);

        $outline->compositeImage($base, Imagick::COMPOSITE_OVER, 0, 0);
        $outline->trimImage(0);
        $outline->setImagePage(0, 0, 0, 0);

        return $outline;
    }

    protected function square(Imagick $logo, int $size, string $bg, float $padding): Imagick
    {
        $inner = (int) round($size * (1 - 2 * $padding));
        $l = clone $logo;
        $l->thumbnailImage($inner, $inner, true);

        $canvas = new Imagick;
        $canvas->newImage($size, $size, new ImagickPixel($bg));
        $canvas->setImageFormat('png');
        $canvas->compositeImage($l, Imagick::COMPOSITE_OVER, (int) (($size - $l->getImageWidth()) / 2), (int) (($size - $l->getImageHeight()) / 2));

        return $canvas;
    }
}
