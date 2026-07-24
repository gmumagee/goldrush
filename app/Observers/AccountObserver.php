<?php

namespace App\Observers;

use App\Jobs\ImportDefaultCatalogForAccount;
use App\Models\Account;
use App\Models\Location;

class AccountObserver
{
    public function created(Account $account): void
    {
        Location::ensureInventoryLocationForAccount($account->id);
        ImportDefaultCatalogForAccount::dispatch($account->id)->afterCommit();
    }
}
