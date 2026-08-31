<?php

use Illuminate\Support\Facades\Schedule;

/*
| Bank nie ma webhooków - wszystko idzie odpytywaniem. Pobranie jest tanie
| i deduplikowane, więc lepiej pytać za często niż za rzadko.
*/
Schedule::command('bnp:pull-history')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('masscollect:match')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('bnp:sync-accounts')->hourly()->withoutOverlapping();
Schedule::command('masscollect:expire')->everyThirtyMinutes();
