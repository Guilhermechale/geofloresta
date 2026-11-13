<?php

namespace App\Models;

use App\Models\Concerns\IsContent;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use IsContent;

    protected $guarded = ['id'];

    public array $translatable = ['title', 'summary', 'body', 'seo_title', 'seo_description'];

    protected $requiredTranslations = ['title', 'summary'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'needs_review' => 'boolean'];
    }
}
