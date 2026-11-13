<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imagick;

/** Converte imagens enviadas no painel para WebP (qualidade 82, lado maior até 2400 px), sem metadados EXIF. */
class ImageOptimizer
{
    public static function storeAsWebp(UploadedFile $file, string $dir = 'images', int $max = 2400): string
    {
        $name = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'imagem';
        $path = trim($dir, '/').'/'.$name.'-'.Str::lower(Str::random(6)).'.webp';

        $img = new Imagick($file->getRealPath());
        $img->autoOrient();
        $img->stripImage();
        if ($img->getImageWidth() > $max || $img->getImageHeight() > $max) {
            $img->resizeImage($max, $max, Imagick::FILTER_LANCZOS, 1, true);
        }
        $img->setImageFormat('webp');
        $img->setImageCompressionQuality(82);

        Storage::disk('public')->put($path, $img->getImagesBlob());

        return $path;
    }

    /** Dimensões de uma imagem do disco público (para width/height no HTML, evita CLS). */
    public static function dimensions(?string $path): ?array
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }
        $size = @getimagesize(Storage::disk('public')->path($path));

        return $size ? ['width' => $size[0], 'height' => $size[1]] : null;
    }
}
