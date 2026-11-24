<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Recordatorio de un día antes del calendario oficial (08:00 hora de Cancún). Requiere el cron de `schedule:run`.
Schedule::command('calendario:recordar')->dailyAt('08:00')->timezone(config('app.display_timezone'))->withoutOverlapping();
