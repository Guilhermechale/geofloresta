<?php

namespace App\Models;

use App\Models\Concerns\IsContent;
use Illuminate\Database\Eloquent\Model;

/** Páginas de texto identificadas por chave (sobre, políticas, acessibilidade...). */
class Page extends Model
{
    use IsContent;

    public const KEYS = ['about', 'privacy', 'cookies', 'terms', 'licences', 'accessibility'];

    protected $guarded = ['id'];

    public array $translatable = ['title', 'summary', 'body', 'seo_title', 'seo_description'];

    protected $requiredTranslations = ['title', 'body'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'content_updated_at' => 'date'];
    }

    public static function forKey(string $key): ?self
    {
        return static::where('key', $key)->published()->first();
    }
}
