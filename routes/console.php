<?php

use Illuminate\Support\Facades\Schedule;

/*
 | Tarefas agendadas. No hPanel há um único cron:
 |   * * * * * cd ~/geofloresta && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
 | Nada pesado corre aqui (rasters processam-se no computador do utilizador, ver pipeline/).
 */

// Prazos de conservação (Política de Privacidade)
Schedule::command('geofloresta:prune')->dailyAt('03:15')->timezone('Africa/Maputo')->withoutOverlapping();
// Focos de incêndio (FIRMS, quase em tempo real) a cada 3 horas; camadas GIBS uma vez por dia
Schedule::command('geofloresta:firms')->cron('7 */3 * * *')->withoutOverlapping(30);
// Focos de todos os países (ficheiros globais FIRMS, ~2 min) e lista de países (Natural Earth, mensal)
Schedule::command('geofloresta:firms-world')->cron('37 */3 * * *')->withoutOverlapping(60);
Schedule::command('geofloresta:world --refresh')->monthlyOn(2, '04:10')->timezone('Africa/Maputo')->withoutOverlapping(30);
Schedule::command('geofloresta:gibs')->dailyAt('05:20')->timezone('Africa/Maputo')->withoutOverlapping(30);

// (As sessões na base de dados são limpas pelo próprio Laravel, por sorteio a cada pedido.)
