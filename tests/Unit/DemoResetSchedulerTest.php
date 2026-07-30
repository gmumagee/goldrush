<?php

namespace Tests\Unit;

use App\Support\DemoResetScheduler;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class DemoResetSchedulerTest extends TestCase
{
    public function test_scheduler_does_not_register_demo_reset_when_demo_mode_is_off(): void
    {
        Config::set('demo.enabled', false);

        $schedule = new Schedule();
        app(DemoResetScheduler::class)->register($schedule);

        $this->assertSame([], array_values(array_filter(
            $schedule->events(),
            fn ($event) => str_contains((string) $event->command, 'demo:reset')
        )));
    }

    public function test_scheduler_registers_demo_reset_when_demo_mode_is_on(): void
    {
        Config::set('demo.enabled', true);
        Config::set('demo.reset_time', '04:00');

        $schedule = new Schedule();
        app(DemoResetScheduler::class)->register($schedule);

        $event = collect($schedule->events())
            ->first(fn ($scheduledEvent) => str_contains((string) $scheduledEvent->command, 'demo:reset'));

        $this->assertNotNull($event);
        $this->assertSame('0 4 * * *', $event->expression);
    }
}
