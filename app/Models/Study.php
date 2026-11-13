<?php

namespace App\Models;

use App\Models\Concerns\IsContent;
use Illuminate\Database\Eloquent\Model;

/** Estudos publicados (artigos científicos migrados). O slug é o do WordPress: /{slug}. */
class Study extends Model
{
    use IsContent;

    public const TYPES = ['original', 'review'];

    protected $guarded = ['id'];

    public array $translatable = ['title', 'summary', 'abstract', 'keywords', 'body', 'seo_title', 'seo_description'];

    public function authorsList(): array
    {
        $a = array_values(array_filter(array_map('trim', preg_split('/\s*;\s*|\R/u', (string) $this->authors))));

        return $a ?: ['GEOFLORESTA'];
    }

    public function keywordList(string $locale): array
    {
        $k = $this->getTranslation('keywords', $locale, false) ?: $this->getTranslation('keywords', 'pt', false);

        return array_values(array_filter(array_map('trim', preg_split('/[;,]\s*/u', (string) $k))));
    }

    public function referenceList(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $this->references))));
    }

    public function related(int $n = 3)
    {
        return static::query()->published()->whereKeyNot($this->getKey())->orderByDesc('published_at')->limit($n)->get();
    }

    protected $searchable = ['title', 'summary', 'body', 'authors'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function url(?string $locale = null): string
    {
        // Os URLs do WordPress (português) mantêm-se; FR/EN têm o seu próprio caminho.
        $locale ??= app()->getLocale();

        return $locale === 'pt' ? url('/'.$this->slug) : lroute('studies.show', ['slug' => $this->slug], $locale);
    }
}
