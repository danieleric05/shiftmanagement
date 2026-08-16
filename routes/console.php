<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nécessite `* * * * * php artisan schedule:run` en cron sur le serveur de production.
Schedule::command('app:envoyer-rappels-entretiens')->dailyAt('08:00');
