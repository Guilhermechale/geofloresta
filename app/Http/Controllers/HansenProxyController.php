<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Ponte de leitura parcial (HTTP Range) para os blocos 10°x10° da perda de cobertura arbórea Hansen/UMD.
 * O armazenamento do Google não autoriza a leitura directa pelo navegador (sem CORS); aqui só se repassam
 * bocados do ficheiro, sem processar nada. Só aceita o ficheiro configurado, um intervalo de cada vez e no
 * máximo 16 MB por pedido.
 */
class HansenProxyController extends Controller
{
    public function __invoke(Request $request, string $tile): Response
    {
        $cfg = config('geofloresta.hansen');
        if (! preg_match('/^bytes=(\d+)-(\d+)$/', (string) $request->header('Range'), $m) || (int) $m[2] < (int) $m[1]) {
            return response('Range header required (bytes=start-end).', 416);
        }
        if ((int) $m[2] - (int) $m[1] + 1 > $cfg['max_bytes']) {
            return response('Range too large.', 416);
        }

        try {
            $up = Http::withHeaders(['Range' => "bytes={$m[1]}-{$m[2]}"])->timeout(30)->retry(2, 400, throw: false)
                ->get(str_replace('{tile}', $tile, $cfg['url']));
        } catch (Throwable) {
            return response('Upstream unavailable.', 502);
        }
        if ($up->status() === 404) {
            return response('No data for this tile (ocean).', 404);
        }
        if (! in_array($up->status(), [200, 206], true)) {
            return response('Upstream error.', 502);
        }

        $body = $up->body();
        if (strlen($body) > $cfg['max_bytes']) {
            return response('Upstream ignored the range.', 502);
        }
        $range = $up->header('Content-Range') ?: sprintf('bytes %d-%d/*', $m[1], (int) $m[1] + strlen($body) - 1);

        return response($body, 206, [
            'Content-Type' => 'image/tiff',
            'Content-Range' => $range,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=604800',
            'X-Robots-Tag' => 'noindex',
            'X-Data-Source' => 'Hansen/UMD/Google/USGS/NASA Global Forest Change '.$cfg['version'].' (CC BY 4.0)',
        ]);
    }
}
