<?php

use App\Console\Commands\AutoScheduleRouteServices;
use App\Console\Commands\PruneAccountBackups;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(AutoScheduleRouteServices::class)
    ->dailyAt('01:00')
    ->timezone((string) config('app.schedule_timezone', config('app.timezone', 'UTC')))
    ->withoutOverlapping();

Schedule::command(PruneAccountBackups::class)
    ->dailyAt('02:00')
    ->timezone((string) config('app.schedule_timezone', config('app.timezone', 'UTC')))
    ->withoutOverlapping();
