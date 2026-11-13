<?php

use Illuminate\Support\Facades\Schedule;

/*
 | Tarefas agendadas. No hPanel há um único cron:
 |   * * * * * cd ~/geofloresta && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
 | Nada pesado corre aqui (rasters processam-se no computador do utilizador, ver pipeline/).
 */

// Prazos de conservação (Política de Privacidade)
Schedule::command('geofloresta:prune')->dailyAt('03:15')->timezone('Africa/Maputo')->withoutOverlapping();
// (As sessões na base de dados são limpas pelo próprio Laravel, por sorteio a cada pedido.)
