<?php

namespace App\Models\Concerns;

use App\Support\Locales;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Translatable\HasTranslations;

/**
 * Comportamento comum ao conteúdo editável:
 * - campos traduzíveis (PT/FR/EN) com spatie/laravel-translatable;
 * - rascunho/publicado;
 * - estado da tradução por idioma ("traduzido / em falta");
 * - texto de pesquisa (FULLTEXT) com todos os idiomas.
 *
 * Cada modelo define $translatable e, opcionalmente, $requiredTranslations e $searchable.
 */
trait IsContent
{
    use HasTranslations;

    public static function bootIsContent(): void
    {
        static::saving(function ($model) {
            if ($model->status === 'published' && ! $model->published_at) {
                $model->published_at = now();
            }
            if ($model->hasSearchText()) {
                $model->search_text = $model->buildSearchText();
            }
        });
    }

    public function hasSearchText(): bool
    {
        return property_exists($this, 'searchable');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published')
            ->where(fn ($w) => $w->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function isPublished(): bool
    {
        return $this->status === 'published' && (! $this->published_at || $this->published_at->isPast());
    }

    /** Campos que contam para "traduzido": por defeito o título. */
    public function requiredTranslationFields(): array
    {
        return property_exists($this, 'requiredTranslations') ? $this->requiredTranslations : ['title'];
    }

    public function hasTranslationFor(string $locale): bool
    {
        foreach ($this->requiredTranslationFields() as $field) {
            if (blank($this->getTranslation($field, $locale, false))) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string,bool> ex.: ['pt' => true, 'fr' => false, 'en' => true] */
    public function translationStatus(): array
    {
        return collect(Locales::all())->mapWithKeys(fn ($l) => [$l => $this->hasTranslationFor($l)])->all();
    }

    /** Idioma em que o conteúdo deve ser mostrado quando falta a tradução pedida. */
    public function displayLocale(string $wanted): string
    {
        if ($this->hasTranslationFor($wanted)) {
            return $wanted;
        }
        $original = $this->original_locale ?? Locales::default();

        return $this->hasTranslationFor($original) ? $original : Locales::default();
    }

    public function buildSearchText(): string
    {
        $parts = [];
        foreach ($this->searchable ?? [] as $field) {
            $value = in_array($field, $this->translatable ?? [], true)
                ? implode(' ', array_filter($this->getTranslations($field)))
                : (string) $this->getAttribute($field);
            $parts[] = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        }

        return mb_substr(implode(' ', array_filter($parts)), 0, 60000);
    }

    /** Título SEO no idioma actual, com recurso ao título. */
    public function seoTitle(): string
    {
        return $this->getTranslation('seo_title', app()->getLocale(), false) ?: (string) $this->getTranslation('title', $this->displayLocale(app()->getLocale()));
    }

    public function seoDescription(): ?string
    {
        $d = $this->getTranslation('seo_description', app()->getLocale(), false);
        if ($d) {
            return $d;
        }
        if (in_array('summary', $this->translatable, true)) {
            $s = trim(\Illuminate\Support\Str::limit(strip_tags((string) $this->getTranslation('summary', $this->displayLocale(app()->getLocale()), false)), 160));

            // Resumo vazio: devolve null para a página usar a sua descrição por omissão
            return $s !== '' ? $s : null;
        }

        return null;
    }
}
