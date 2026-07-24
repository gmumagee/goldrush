<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Location;
use Illuminate\Console\Command;

class InitializeInventoryLocations extends Command
{
    protected $signature = 'locations:init-inventory {--account_id=}';

    protected $description = 'Create the default inventory location for accounts that do not have one.';

    public function handle(): int
    {
        $accountId = $this->resolveAccountIdOption();

        $accounts = Account::query()
            ->when($accountId !== null, fn ($query) => $query->where('id', $accountId))
            ->orderBy('id')
            ->get();

        $createdCount = 0;
        $existingCount = 0;

        foreach ($accounts as $account) {
            $existingLocation = Location::query()
                ->where('account_id', $account->id)
                ->inventory()
                ->first();

            if ($existingLocation) {
                $existingCount++;
                $this->line(sprintf(
                    'Account #%d already has inventory location #%d (%s).',
                    $account->id,
                    $existingLocation->id,
                    $existingLocation->location_name
                ));

                continue;
            }

            $location = Location::ensureInventoryLocationForAccount($account->id);
            $createdCount++;

            $this->info(sprintf(
                'Created inventory location #%d for account #%d.',
                $location->id,
                $account->id
            ));
        }

        $this->line('Accounts processed: '.$accounts->count());
        $this->line('Inventory locations created: '.$createdCount);
        $this->line('Accounts already configured: '.$existingCount);

        return self::SUCCESS;
    }

    protected function resolveAccountIdOption(): ?int
    {
        $accountIdOption = $this->option('account_id');

        if ($accountIdOption === null || $accountIdOption === '') {
            return null;
        }

        if (! is_numeric($accountIdOption) || (int) $accountIdOption < 1) {
            throw new \InvalidArgumentException('The --account_id option must be a positive integer.');
        }

        return (int) $accountIdOption;
    }
}
