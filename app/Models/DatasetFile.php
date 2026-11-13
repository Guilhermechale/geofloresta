<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Spatie\Translatable\HasTranslations;

/**
 * Ficheiro descarregável de um dataset. Fica num disco privado ("datasets"):
 * só se descarrega pelo controlador, que conta o download (anónimo).
 */
class DatasetFile extends Model
{
    use HasTranslations;

    protected $guarded = ['id'];

    public array $translatable = ['label'];

    public static function booted(): void
    {
        // Tamanho e checksum calculados sempre a partir do ficheiro real.
        static::saving(function (self $file) {
            if ($file->isDirty('path') && $file->path && Storage::disk($file->disk)->exists($file->path)) {
                $full = Storage::disk($file->disk)->path($file->path);
                $file->size_bytes = filesize($full);
                $file->sha256 = hash_file('sha256', $full);
                $file->filename = $file->filename ?: basename($file->path);
            }
        });
    }

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    public function formatLabel(): string
    {
        return Dataset::FORMATS[$this->format] ?? strtoupper($this->format);
    }
}
