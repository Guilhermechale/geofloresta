<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Definições do site (chave → valor JSON). Textos traduzíveis guardam {"pt","fr","en"}.
 * Lidas com cache; a cache limpa-se ao gravar.
 */
class Setting extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public static function booted(): void
    {
        static::saved(fn () => Cache::forget('settings.all'));
        static::deleted(fn () => Cache::forget('settings.all'));
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::rememberForever('settings.all', fn () => static::query()->pluck('value', 'key')->all());
        $value = $all[$key] ?? null;
        if (is_string($value)) {
            $value = json_decode($value, true) ?? $value;
        }

        return $value ?? $default;
    }

    /** Texto traduzido de uma definição, com recurso ao português. */
    public static function text(string $key, ?string $locale = null): ?string
    {
        $v = static::get($key);
        if (! is_array($v)) {
            return $v;
        }
        $locale ??= app()->getLocale();

        return $v[$locale] ?? $v['pt'] ?? null;
    }
}
