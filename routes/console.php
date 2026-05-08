<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('backup:run --only-db')->daily()->at('20:00');
