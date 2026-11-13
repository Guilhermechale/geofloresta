<?php

namespace App\Models;

use App\Models\Concerns\IsContent;
use App\Models\Concerns\RedirectsOldSlugs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tutorial extends Model
{
    use IsContent, RedirectsOldSlugs;

    public const PLATFORMS = ['r' => 'R', 'colab' => 'Google Colab', 'gee' => 'Google Earth Engine', 'qgis' => 'QGIS', 'web' => 'Ferramentas web', 'other' => 'Geral'];

    public const LEVELS = ['beginner', 'intermediate', 'advanced'];

    protected $guarded = ['id'];

    public array $translatable = ['slug', 'title', 'summary', 'prerequisites', 'objectives', 'body', 'exercise', 'takeaways', 'seo_title', 'seo_description'];

    /** Linhas não vazias de um campo de texto (objectivos, pré-requisitos, pontos-chave). */
    public function lines(string $field, string $locale): array
    {
        $v = $this->isTranslatableAttribute($field) ? $this->getTranslation($field, $locale, false) : $this->getAttribute($field);
        if (! $v && $this->isTranslatableAttribute($field)) {
            $v = $this->getTranslation($field, 'pt', false);
        }

        return array_values(array_filter(array_map(fn ($l) => trim(preg_replace('/^[\-•*]\s*/u', '', $l)), preg_split('/\R/u', (string) $v))));
    }

    /** Fontes: uma por linha, "Título | URL" (URL opcional). */
    public function sourceList(): array
    {
        return array_map(function ($l) {
            [$t, $u] = array_pad(array_map('trim', explode('|', $l, 2)), 2, null);

            return ['title' => $t, 'url' => $u && filter_var($u, FILTER_VALIDATE_URL) ? $u : null];
        }, $this->lines('sources', 'pt'));
    }

    /** Tutoriais seguintes (mesma plataforma primeiro). */
    public function related(int $n = 3)
    {
        return static::query()->published()->whereKeyNot($this->getKey())
            ->orderByRaw('platform = ? desc', [$this->platform])->orderByDesc('published_at')->limit($n)->get();
    }

    protected $requiredTranslations = ['title', 'body', 'slug'];

    protected $searchable = ['title', 'summary', 'body', 'platform'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'content_updated_at' => 'date', 'featured' => 'boolean'];
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TutorialAttachment::class)->orderBy('sort');
    }

    public function publicPath(string $locale, string $slug): string
    {
        return $this->sectionPath($locale, 'learn', $slug);
    }

    /** Tutoriais migrados mantêm o URL do WordPress em português (/{legacy_slug}). */
    public function url(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        if ($locale === 'pt' && $this->legacy_slug) {
            return url('/'.$this->legacy_slug);
        }
        $slug = $this->getTranslation('slug', $locale, false) ?: ($this->legacy_slug ?: $this->getTranslation('slug', 'pt'));

        return lroute('learn.show', ['slug' => $slug], $locale);
    }
}
