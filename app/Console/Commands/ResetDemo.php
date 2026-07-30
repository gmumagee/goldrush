<?php

namespace App\Console\Commands;

use App\Services\DemoResetService;
use App\Support\Demo;
use Illuminate\Console\Command;
use Throwable;

class ResetDemo extends Command
{
    protected $signature = 'demo:reset';

    protected $description = 'Reset the shared demo tenant back to its seeded baseline.';

    public function handle(DemoResetService $demoResetService): int
    {
        if (! Demo::isEnabled()) {
            $this->error('Demo reset refused because APP_DEMO_MODE is disabled.');

            return self::FAILURE;
        }

        try {
            $demoResetService->reset();
        } catch (Throwable $exception) {
            $this->error('Demo reset failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Demo tenant reset to the seeded baseline.');

        return self::SUCCESS;
    }
}
