<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Redirect extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['last_hit_at' => 'datetime'];
    }

    /** Normaliza o caminho: começa com "/", sem barra final, sem domínio, sem query. */
    public static function normalize(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH) ?: '/';
        $path = '/'.trim(rawurldecode($path), '/');

        return $path === '' ? '/' : $path;
    }

    protected function fromPath(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(set: fn ($v) => static::normalize((string) $v));
    }
}
