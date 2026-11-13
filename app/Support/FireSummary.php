<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Resumo dos focos de incêndio, lido do ficheiro em cache gerado pelo comando agendado (M7).
 * Nunca chama a API durante um pedido de página.
 */
class FireSummary
{
    public const PATH = 'firms/summary.json';

    /** @return array{count_24h:int, updated_at:string, sources:array}|null */
    public static function read(): ?array
    {
        $disk = Storage::disk('local');
        if (! $disk->exists(self::PATH)) {
            return null;
        }
        $data = json_decode((string) $disk->get(self::PATH), true);

        return is_array($data) && isset($data['count_24h'], $data['updated_at']) ? $data : null;
    }
}
