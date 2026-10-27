<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Rappels SMS des rendez-vous du lendemain (sur le serveur : une tâche cron lance « php artisan schedule:run » chaque minute)
\Illuminate\Support\Facades\Schedule::command('cabinet:rappels')->dailyAt(config('sms.heure', '18:00'))->withoutOverlapping();
