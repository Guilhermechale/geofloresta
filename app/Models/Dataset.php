<?php

namespace App\Models;

use App\Models\Concerns\IsContent;
use App\Models\Concerns\RedirectsOldSlugs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dataset extends Model
{
    use IsContent, RedirectsOldSlugs;

    public const THEMES = ['land-cover', 'forest-loss', 'fire', 'hydrology', 'biomass', 'boundaries', 'climate'];

    public const REGIONS = ['mozambique', 'niassa', 'cabo-delgado', 'nampula', 'zambezia', 'tete', 'manica', 'sofala', 'inhambane', 'gaza', 'maputo-province', 'maputo-city'];

    public const FORMATS = ['cog' => 'GeoTIFF (COG)', 'geojson' => 'GeoJSON', 'gpkg' => 'GeoPackage', 'shp' => 'Shapefile (ZIP)', 'csv' => 'CSV'];

    protected $guarded = ['id'];

    public array $translatable = ['slug', 'title', 'summary', 'description', 'processing', 'limitations', 'seo_title', 'seo_description'];

    protected $requiredTranslations = ['title', 'summary', 'slug'];

    protected $searchable = ['title', 'summary', 'description', 'source_name', 'theme', 'region'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'accessed_at' => 'date', 'featured' => 'boolean'];
    }

    public function files(): HasMany
    {
        return $this->hasMany(DatasetFile::class)->orderBy('sort');
    }

    public function isExternal(): bool
    {
        return $this->hosting === 'external';
    }

    public function publicPath(string $locale, string $slug): string
    {
        return $this->sectionPath($locale, 'data', $slug);
    }

    public function url(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $slug = $this->getTranslation('slug', $locale, false) ?: $this->getTranslation('slug', 'pt');

        return lroute('data.show', ['slug' => $slug], $locale);
    }

    /** Nome legível da licença (os identificadores SPDX padrão ficam como estão). */
    public function licenceLabel(): string
    {
        $key = 'data.licence_names.'.$this->licence_spdx;

        return trans()->has($key) ? __($key) : (string) $this->licence_spdx;
    }

    public function period(): ?string
    {
        if (! $this->year_start) {
            return null;
        }

        return $this->year_end && $this->year_end !== $this->year_start ? "{$this->year_start}–{$this->year_end}" : (string) $this->year_start;
    }
}
