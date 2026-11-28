<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Vigilância diária das fontes de dados das ferramentas que correm no navegador (Monitor de desmatamento e
 * uso do solo): catálogos STAC, leitura parcial das imagens Sentinel-2 e Sentinel-1, chave de acesso do
 * Planetary Computer e blocos Hansen. Se algo falhar (ou voltar a funcionar), envia um e-mail ao administrador.
 * Também avisa quando sai uma nova versão anual do Hansen (para actualizar a configuração).
 */
class CheckSources extends Command
{
    protected $signature = 'geofloresta:check-sources {--no-mail : Só mostra o resultado}';

    protected $description = 'Testa as fontes de dados do monitor de desmatamento e avisa por e-mail se falharem';

    public const PATH = 'fontes/vigilancia.json';

    /** Estado guardado pela última verificação (para a faixa «Estado dos dados» das ferramentas), ou null. */
    public static function read(): ?array
    {
        $disk = Storage::disk('local');

        return $disk->exists(self::PATH) ? json_decode($disk->get(self::PATH), true) : null;
    }

    protected const S2 = 'https://earth-search.aws.element84.com/v1/search';

    protected const S1 = 'https://planetarycomputer.microsoft.com/api/stac/v1/search';

    protected const TOKEN = 'https://planetarycomputer.microsoft.com/api/sas/v1/token/sentinel-1-rtc';

    public function handle(): int
    {
        $bbox = [36.49, -16.56, 36.56, -16.49]; // Zambézia, Moçambique (local de validação)
        $from = now('UTC')->subDays(30)->toDateString().'T00:00:00Z/'.now('UTC')->toDateString().'T23:59:59Z';
        $checks = [];
        $check = function (string $name, callable $fn) use (&$checks) {
            try {
                $detail = $fn();
                $checks[$name] = ['ok' => true, 'detail' => $detail];
            } catch (Throwable $e) {
                $checks[$name] = ['ok' => false, 'detail' => mb_substr($e->getMessage(), 0, 300)];
            }
        };

        $s2 = null;
        $check('sentinel2_catalogue', function () use ($bbox, $from, &$s2) {
            $r = Http::timeout(30)->retry(2, 2000)->post(self::S2, ['collections' => ['sentinel-2-l2a'], 'bbox' => $bbox, 'datetime' => $from, 'limit' => 50]);
            $f = collect($r->json('features') ?? [])->sortByDesc('properties.datetime')->values();
            throw_if($f->isEmpty(), new \RuntimeException('no Sentinel-2 items in the last 30 days'));
            $s2 = $f->first();
            $age = now('UTC')->diffInDays($s2['properties']['datetime']);
            throw_if(abs($age) > 12, new \RuntimeException("latest Sentinel-2 item is {$age} days old"));

            return 'latest '.$s2['properties']['datetime'];
        });
        $check('sentinel2_image', function () use (&$s2) {
            throw_unless($s2, new \RuntimeException('no item to read'));

            return $this->rangeRead($s2['assets']['nir']['href']);
        });

        $s1 = null;
        $check('sentinel1_catalogue', function () use ($bbox, $from, &$s1) {
            $r = Http::timeout(30)->retry(2, 2000)->post(self::S1, ['collections' => ['sentinel-1-rtc'], 'bbox' => $bbox, 'datetime' => $from, 'limit' => 50]);
            $f = collect($r->json('features') ?? [])->sortByDesc('properties.datetime')->values();
            throw_if($f->isEmpty(), new \RuntimeException('no Sentinel-1 RTC items in the last 30 days'));
            $s1 = $f->first();
            $age = now('UTC')->diffInDays($s1['properties']['datetime']);
            throw_if(abs($age) > 20, new \RuntimeException("latest Sentinel-1 item is {$age} days old"));

            return 'latest '.$s1['properties']['datetime'];
        });
        $token = null;
        $check('sentinel1_token', function () use (&$token) {
            $token = Http::timeout(20)->retry(2, 2000)->get(self::TOKEN)->throw()->json('token');
            throw_unless($token, new \RuntimeException('empty token'));

            return 'ok';
        });
        $check('sentinel1_image', function () use (&$s1, &$token) {
            throw_unless($s1 && $token, new \RuntimeException('no item or token'));

            return $this->rangeRead(($s1['assets']['vh']['href'] ?? $s1['assets']['hv']['href']).'?'.$token);
        });
        $check('hansen_tiles', fn () => $this->rangeRead(str_replace('{tile}', '10S_030E', config('geofloresta.hansen.url'))));
        // relevo (Terrain Tiles) e imagens diárias da NASA (GIBS)
        $check('terrain_tiles', function () {
            $r = Http::timeout(20)->retry(2, 2000)->get('https://elevation-tiles-prod.s3.amazonaws.com/terrarium/0/0/0.png');
            throw_unless($r->successful() && str_starts_with($r->body(), "\x89PNG"), new \RuntimeException('HTTP '.$r->status()));

            return 'ok';
        });
        $check('nasa_gibs', function () {
            $day = now('UTC')->subDays(2)->toDateString();
            $r = Http::timeout(20)->retry(2, 2000)->get("https://gibs.earthdata.nasa.gov/wmts/epsg3857/best/MODIS_Terra_CorrectedReflectance_TrueColor/default/{$day}/GoogleMapsCompatible_Level9/0/0/0.jpg");
            throw_unless($r->successful() && str_starts_with($r->body(), "\xFF\xD8"), new \RuntimeException('HTTP '.$r->status()));

            return 'ok';
        });

        // Nova versão anual do Hansen? (ex.: GFC-2026-v1.14)
        $hansen = config('geofloresta.hansen');
        $nextYear = $hansen['last_year'] + 1;
        $nextVersion = preg_replace_callback('/v(\d+)\.(\d+)/', fn ($m) => 'v'.$m[1].'.'.($m[2] + 1), $hansen['version']);
        $newHansen = null;
        try {
            $u = "https://storage.googleapis.com/earthenginepartners-hansen/GFC-{$nextYear}-{$nextVersion}/download.html";
            if (Http::timeout(15)->head($u)->successful()) {
                $newHansen = "GFC-{$nextYear}-{$nextVersion}";
            }
        } catch (Throwable) {
        }

        $failed = collect($checks)->filter(fn ($c) => ! $c['ok'])->keys()->all();
        $prev = Storage::disk('local')->exists(self::PATH) ? json_decode(Storage::disk('local')->get(self::PATH), true) : [];
        $state = [
            'checked_at' => now()->toIso8601String(), 'ok' => ! $failed, 'failed' => $failed, 'checks' => $checks,
            's2_latest' => $s2['properties']['datetime'] ?? ($prev['s2_latest'] ?? null),
            's1_latest' => $s1['properties']['datetime'] ?? ($prev['s1_latest'] ?? null),
            'hansen' => ['version' => $hansen['version'], 'last_year' => $hansen['last_year']],
            'new_hansen' => $newHansen, 'mailed_at' => $prev['mailed_at'] ?? null, 'hansen_mailed' => $prev['hansen_mailed'] ?? null,
        ];

        foreach ($checks as $name => $c) {
            $this->line(sprintf('%s %-22s %s', $c['ok'] ? '✓' : '✗', $name, $c['detail']));
        }
        if ($newHansen) {
            $this->warn("Nova versão Hansen disponível: {$newHansen}");
        }

        // E-mail: quando começa a falhar (ou continua a falhar há 3 dias), quando volta a funcionar, e uma vez por nova versão Hansen
        $to = config('geofloresta.contact_email');
        $lines = [];
        if ($failed && (($prev['ok'] ?? true) || ! $state['mailed_at'] || now()->diffInHours($state['mailed_at']) >= 72)) {
            $lines[] = 'Uma ou mais fontes de dados do Monitor de desmatamento / uso do solo deixaram de responder:';
            foreach ($failed as $f) {
                $lines[] = "- {$f}: {$checks[$f]['detail']}";
            }
            $lines[] = '';
            $lines[] = 'A ferramenta avisa os utilizadores e continua com as fontes que funcionam. Se a falha persistir, pode ser preciso actualizar um endereço.';
        } elseif (! $failed && ($prev['ok'] ?? true) === false) {
            $lines[] = 'Todas as fontes de dados voltaram a funcionar.';
        }
        if ($newHansen && ($prev['hansen_mailed'] ?? null) !== $newHansen) {
            $lines[] = '';
            $lines[] = "Saiu uma nova versão da perda de cobertura arbórea Hansen/UMD: {$newHansen}. Para a usar, actualize HANSEN_VERSION, HANSEN_LAST_YEAR e HANSEN_URL no .env.";
            $state['hansen_mailed'] = $newHansen;
        }
        if ($lines && ! $this->option('no-mail') && $to) {
            try {
                Mail::raw(implode("\n", $lines)."\n\nVerificado em ".now()->format('Y-m-d H:i').' (geofloresta:check-sources).', function ($m) use ($to, $failed) {
                    $m->to($to)->subject($failed ? 'GEOFLORESTA: fonte de dados com falha' : 'GEOFLORESTA: fontes de dados');
                });
                $state['mailed_at'] = now()->toIso8601String();
            } catch (Throwable $e) {
                report($e);
            }
        }
        Storage::disk('local')->put(self::PATH, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** Lê os primeiros 16 bytes de um GeoTIFF por pedido parcial (o que os navegadores fazem). */
    protected function rangeRead(string $url): string
    {
        $r = Http::timeout(30)->retry(2, 2000)->withHeaders(['Range' => 'bytes=0-15'])->get($url);
        throw_unless($r->status() === 206, new \RuntimeException('HTTP '.$r->status()));
        $magic = substr($r->body(), 0, 4);
        throw_unless(in_array($magic, ["II*\0", "MM\0*", "II+\0", "MM\0+"], true), new \RuntimeException('not a TIFF'));

        return 'range ok';
    }
}
