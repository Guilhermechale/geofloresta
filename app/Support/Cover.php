<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Imagick;
use Throwable;

/**
 * Imagens de destaque (capas) de tutoriais e pesquisas.
 * Gera, na primeira vez, uma versão reduzida em WebP (thumbs/{largura}/...) e devolve URL e dimensões.
 */
class Cover
{
    /** @return array{src: string, width: int, height: int}|null */
    public static function image(?string $path, int $width): ?array
    {
        if (! $path) {
            return null;
        }
        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return null;
        }

        $thumb = 'thumbs/'.$width.'/'.preg_replace('/\.[a-z0-9]+$/i', '', $path).'.webp';
        if (! $disk->exists($thumb) || $disk->lastModified($thumb) < $disk->lastModified($path)) {
            try {
                $img = new Imagick($disk->path($path));
                $img->autoOrient();
                $img->stripImage();
                if ($img->getImageWidth() > $width) {
                    $img->resizeImage($width, 0, Imagick::FILTER_LANCZOS, 1);
                }
                $img->setImageFormat('webp');
                $img->setImageCompressionQuality(80);
                $disk->put($thumb, $img->getImagesBlob());
            } catch (Throwable $e) {
                report($e);
                $thumb = $path;
            }
        }

        $size = @getimagesize($disk->path($thumb));

        // ?v= muda quando a capa é substituída (o navegador não mostra a versão antiga em cache)
        return $size ? ['src' => asset('storage/'.$thumb).'?v='.$disk->lastModified($thumb), 'width' => $size[0], 'height' => $size[1]] : null;
    }
}
