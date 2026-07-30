<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\InteractsWithDemoData;
use Illuminate\Database\Seeder;

abstract class AbstractDemoSeeder extends Seeder
{
    use InteractsWithDemoData;
}
