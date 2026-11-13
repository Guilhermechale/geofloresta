<?php

namespace App\Models;

use App\Models\Concerns\IsContent;
use Illuminate\Database\Eloquent\Model;

/** Estudos publicados (artigos científicos migrados). O slug é o do WordPress: /{slug}. */
class Study extends Model
{
    use IsContent;

    protected $guarded = ['id'];

    public array $translatable = ['title', 'summary', 'body', 'seo_title', 'seo_description'];

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
