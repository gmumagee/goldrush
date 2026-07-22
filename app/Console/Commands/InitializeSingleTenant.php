<?php

namespace App\Console\Commands;

use App\Support\Tenancy;
use Illuminate\Console\Command;

class InitializeSingleTenant extends Command
{
    protected $signature = 'tenancy:init-single {account_name : Name for the single account}';

    protected $description = 'Create the configured single-tenant account if it does not exist.';

    public function handle(): int
    {
        $account = Tenancy::singleAccount();

        if ($account) {
            $this->info(sprintf(
                'Single-tenant account already exists: #%d %s',
                $account->id,
                $account->account_name
            ));

            return self::SUCCESS;
        }

        $account = Tenancy::ensureSingleAccount((string) $this->argument('account_name'));

        $this->info(sprintf(
            'Created single-tenant account #%d (%s).',
            $account->id,
            $account->account_name
        ));

        return self::SUCCESS;
    }
}
