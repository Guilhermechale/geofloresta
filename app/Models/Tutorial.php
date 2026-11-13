<?php

namespace App\Models;

use App\Models\Concerns\IsContent;
use App\Models\Concerns\RedirectsOldSlugs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tutorial extends Model
{
    use IsContent, RedirectsOldSlugs;

    public const PLATFORMS = ['r' => 'R', 'colab' => 'Google Colab', 'gee' => 'Google Earth Engine', 'qgis' => 'QGIS', 'other' => 'Geral'];

    public const LEVELS = ['beginner', 'intermediate', 'advanced'];

    protected $guarded = ['id'];

    public array $translatable = ['slug', 'title', 'summary', 'prerequisites', 'body', 'seo_title', 'seo_description'];

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
