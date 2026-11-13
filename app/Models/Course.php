<?php

namespace App\Models;

use App\Models\Concerns\IsContent;
use App\Models\Concerns\RedirectsOldSlugs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use IsContent, RedirectsOldSlugs;

    protected $guarded = ['id'];

    public array $translatable = ['slug', 'title', 'summary', 'seo_title', 'seo_description'];

    protected $requiredTranslations = ['title', 'slug'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function modules(): HasMany
    {
        return $this->hasMany(CourseModule::class)->orderBy('sort');
    }

    public function publicPath(string $locale, string $slug): string
    {
        return $this->sectionPath($locale, 'courses', $slug);
    }

    public function url(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $slug = $this->getTranslation('slug', $locale, false) ?: $this->getTranslation('slug', 'pt');

        return lroute('courses.show', ['slug' => $slug], $locale);
    }
}
