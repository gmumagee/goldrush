<?php

namespace Tests\Unit;

use App\Support\AppDateTime;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class AppDateTimeTest extends TestCase
{
    public function test_display_methods_follow_agent_formats_and_fallbacks(): void
    {
        config(['app.timezone' => 'UTC']);

        $value = CarbonImmutable::parse('2026-07-19 14:30:45', 'UTC');

        $this->assertSame('07-19-2026', AppDateTime::displayDate($value));
        $this->assertSame('14:30:45', AppDateTime::displayTime($value));
        $this->assertSame('2026-07-19', AppDateTime::isoDate($value));
        $this->assertSame('2026-07-19T14:30:45+00:00', AppDateTime::isoDateTime($value));
        $this->assertSame('—', AppDateTime::displayDate(null));
        $this->assertSame('—', AppDateTime::displayTime(null));
        $this->assertNull(AppDateTime::isoDate(null));
        $this->assertNull(AppDateTime::isoDateTime(null));
    }

    public function test_display_methods_use_the_configured_application_timezone(): void
    {
        config(['app.timezone' => 'America/Toronto']);

        $value = CarbonImmutable::parse('2026-07-19 03:15:30', 'UTC');

        $this->assertSame('07-18-2026', AppDateTime::displayDate($value));
        $this->assertSame('23:15:30', AppDateTime::displayTime($value));
        $this->assertSame('2026-07-18', AppDateTime::isoDate($value));
        $this->assertSame('2026-07-18T23:15:30-04:00', AppDateTime::isoDateTime($value));
    }
}
