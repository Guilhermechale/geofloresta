<?php

namespace App\Models;

use App\Models\Concerns\IsContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Estudos publicados (artigos científicos migrados). O slug é o do WordPress: /{slug}. */
class Study extends Model
{
    use IsContent;

    public const TYPES = ['original', 'review'];

    /** Idiomas com endereço próprio (o português usa o slug do WordPress, na coluna slug). */
    public const LOCALISED = ['fr', 'en'];

    protected static function booted(): void
    {
        // Gera o endereço FR/EN a partir do título traduzido, uma única vez (depois fica fixo).
        static::saving(function (Study $s) {
            $slugs = $s->slugs ?? [];
            foreach (self::LOCALISED as $l) {
                $title = $s->getTranslation('title', $l, false);
                if (empty($slugs[$l]) && filled($title)) {
                    $taken = static::query()->whereKeyNot($s->getKey())->get()->map(fn ($o) => $o->slugs[$l] ?? null)->filter()->values()->all();
                    $slugs[$l] = self::uniqueSlug(self::makeSlug($title), $l, [$l => $taken]);
                }
            }
            $s->slugs = $slugs ?: null;
        });
    }

    public static function makeSlug(string $title, int $max = 90): string
    {
        $slug = Str::slug($title);
        if (strlen($slug) > $max) {
            $slug = substr($slug, 0, $max);
            $slug = substr($slug, 0, strrpos($slug, '-') ?: $max);
        }

        return $slug;
    }

    /** @param array<string, list<string>> $used */
    public static function uniqueSlug(string $slug, string $locale, array $used): string
    {
        $base = $slug;
        for ($i = 2; in_array($slug, $used[$locale] ?? [], true); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    /** Slug usado no endereço de um idioma. */
    public function localSlug(string $locale): string
    {
        return $locale === 'pt' ? $this->slug : ($this->slugs[$locale] ?? $this->slug);
    }

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
        return ['published_at' => 'datetime', 'slugs' => 'array'];
    }

    public function url(?string $locale = null): string
    {
        // Os URLs do WordPress (português) mantêm-se; FR/EN têm o seu próprio caminho.
        $locale ??= app()->getLocale();

        return $locale === 'pt' ? url('/'.$this->slug) : lroute('studies.show', ['slug' => $this->localSlug($locale)], $locale);
    }
}
