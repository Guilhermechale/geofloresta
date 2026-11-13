<?php

namespace App\Console\Commands;

use App\Models\ConsentLog;
use App\Models\ContactMessage;
use Illuminate\Console\Command;

/** Aplica os prazos de conservação da Política de Privacidade (24 meses). */
class PrunePersonalData extends Command
{
    protected $signature = 'geofloresta:prune {--months=24}';

    protected $description = 'Apaga mensagens de contacto e registos de consentimento com mais de 24 meses';

    public function handle(): int
    {
        $limit = now()->subMonths((int) $this->option('months'));
        $m = ContactMessage::where('created_at', '<', $limit)->delete();
        $c = ConsentLog::where('created_at', '<', $limit)->delete();
        $this->info("Apagados: {$m} mensagens, {$c} registos de consentimento (anteriores a {$limit->toDateString()}).");

        return self::SUCCESS;
    }
}
