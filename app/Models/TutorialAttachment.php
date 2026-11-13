<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Spatie\Translatable\HasTranslations;

class TutorialAttachment extends Model
{
    use HasTranslations;

    protected $guarded = ['id'];

    public array $translatable = ['label'];

    public static function booted(): void
    {
        static::saving(function (self $a) {
            if ($a->isDirty('path') && $a->path && Storage::disk('public')->exists($a->path)) {
                $a->size_bytes = Storage::disk('public')->size($a->path);
                $a->filename = $a->filename ?: basename($a->path);
            }
        });
    }

    public function tutorial(): BelongsTo
    {
        return $this->belongsTo(Tutorial::class);
    }
}
