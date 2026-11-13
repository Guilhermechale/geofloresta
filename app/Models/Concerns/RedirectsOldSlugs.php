<?php

namespace App\Models\Concerns;

use App\Models\Redirect;
use App\Support\Locales;

/**
 * Histórico de slugs: quando o slug de um conteúdo publicado muda num idioma,
 * cria uma redirecção 301 do URL antigo para o novo (e apaga redirecções que
 * apontem do novo URL, para não haver ciclos).
 *
 * O modelo define publicPath(string $locale, string $slug): string.
 */
trait RedirectsOldSlugs
{
    public static function bootRedirectsOldSlugs(): void
    {
        static::updating(function ($model) {
            if (! $model->isDirty('slug') || $model->getOriginal('status') !== 'published') {
                return;
            }
            $old = json_decode((string) $model->getRawOriginal('slug'), true) ?: [];
            $new = $model->getTranslations('slug');

            foreach (Locales::all() as $locale) {
                $from = $old[$locale] ?? null;
                $to = $new[$locale] ?? null;
                if (! $from || ! $to || $from === $to) {
                    continue;
                }
                $fromPath = $model->publicPath($locale, $from);
                $toPath = $model->publicPath($locale, $to);

                Redirect::where('from_path', $toPath)->delete();
                Redirect::updateOrCreate(
                    ['from_path' => $fromPath],
                    ['to_url' => $toPath, 'status_code' => 301, 'source' => 'slug'],
                );
                // Encadear: quem apontava para o URL antigo passa a apontar para o novo
                Redirect::where('to_url', $fromPath)->update(['to_url' => $toPath]);
            }
        });
    }

    /** Caminho público com o prefixo do idioma e o segmento traduzido da secção. */
    protected function sectionPath(string $locale, string $routeKey, string $slug): string
    {
        $prefix = Locales::prefix($locale);
        $section = trans("routes.{$routeKey}", [], $locale);

        return '/'.ltrim(($prefix ? $prefix.'/' : '').$section.'/'.$slug, '/');
    }
}
