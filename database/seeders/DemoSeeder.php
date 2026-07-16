<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\InteractsWithDemoData;
use Illuminate\Database\Seeder;

abstract class DemoSeeder extends Seeder
{
    use InteractsWithDemoData;
}
