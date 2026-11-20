<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('backup:run --only-db')->daily()->at('20:00');

// Cada 10 minutos revisa si toca sincronizar (el intervalo real lo define SURVEY123_SYNC_CADA_MINUTOS).
Schedule::command('survey123:sincronizar-programado')->everyTenMinutes()->withoutOverlapping();
