<?php

namespace Tests\Unit;

use App\Support\Tenancy;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class TenancyTest extends TestCase
{
    public function test_it_reports_single_mode_correctly(): void
    {
        Config::set('tenancy.mode', Tenancy::MODE_SINGLE);

        $this->assertTrue(Tenancy::isSingle());
        $this->assertFalse(Tenancy::isMulti());
    }

    public function test_it_reports_multi_mode_correctly(): void
    {
        Config::set('tenancy.mode', Tenancy::MODE_MULTI);

        $this->assertTrue(Tenancy::isMulti());
        $this->assertFalse(Tenancy::isSingle());
    }
}
