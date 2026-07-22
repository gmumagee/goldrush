<?php

namespace App\Observers;

use App\Jobs\ImportDefaultCatalogForAccount;
use App\Models\Account;

class AccountObserver
{
    public function created(Account $account): void
    {
        ImportDefaultCatalogForAccount::dispatch($account->id)->afterCommit();
    }
}
