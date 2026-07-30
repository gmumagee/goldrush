<?php

namespace App\Support;

use App\Console\Commands\ResetDemo;
use Illuminate\Console\Scheduling\Schedule;

class DemoResetScheduler
{
    public function register(Schedule $schedule): void
    {
        if (! Demo::isEnabled()) {
            return;
        }

        $schedule->command(ResetDemo::class)
            ->dailyAt(Demo::resetTime())
            ->timezone((string) config('app.schedule_timezone', config('app.timezone', 'UTC')))
            ->withoutOverlapping();
    }
}
