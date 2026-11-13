<?php

namespace App\Http\Controllers;

use App\Models\DatasetFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Downloads só por aqui (os ficheiros estão num disco privado).
 * Contagem anónima: incrementa um contador por ficheiro — sem IP, sem cookies, sem registo.
 */
class DownloadController extends Controller
{
    public function __invoke(DatasetFile $file, string $filename): StreamedResponse
    {
        $file->loadMissing('dataset');
        abort_unless($file->filename === $filename, 404);
        abort_unless($file->dataset && $file->dataset->isPublished() && ! $file->dataset->isExternal(), 404);
        $disk = Storage::disk($file->disk);
        abort_unless($disk->exists($file->path), 404);

        DatasetFile::whereKey($file->id)->increment('download_count');

        return $disk->download($file->path, $file->filename, [
            'Content-Type' => 'application/zip',
            'X-Robots-Tag' => 'noindex',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
