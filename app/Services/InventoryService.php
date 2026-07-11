<?php

namespace App\Services;

use App\Models\Bin;
use App\Models\Machine;
use App\Models\Transaction;
use Illuminate\Support\Collection;

class InventoryService
{
    public function getCurrentInventoryForBin(Bin $bin): int
    {
        $transactions = Transaction::query()
            ->select(['id', 'bin_id', 'transaction_type', 'quantity', 'transaction_at'])
            ->where('account_id', $bin->account_id)
            ->where('bin_id', $bin->id)
            ->orderBy('transaction_at')
            ->orderBy('id')
            ->get();

        return $this->calculateCurrentInventory($transactions);
    }

    public function getCurrentInventoryForMachine(Machine $machine): array
    {
        $bins = $machine->relationLoaded('bins')
            ? $machine->bins
            : $machine->bins()
                ->where('account_id', $machine->account_id)
                ->get(['id', 'account_id']);

        if ($bins->isEmpty()) {
            return [];
        }

        return $this->getCurrentInventoryForBins($bins, $machine->account_id);
    }

    public function getCurrentInventoryForBins(iterable $bins, ?int $accountId = null): array
    {
        $bins = $bins instanceof Collection ? $bins->values() : collect($bins)->values();

        if ($bins->isEmpty()) {
            return [];
        }

        $accountId ??= (int) $bins->first()->account_id;

        $transactionsByBin = Transaction::query()
            ->select(['id', 'bin_id', 'transaction_type', 'quantity', 'transaction_at'])
            ->where('account_id', $accountId)
            ->whereIn('bin_id', $bins->pluck('id'))
            ->orderBy('bin_id')
            ->orderBy('transaction_at')
            ->orderBy('id')
            ->get()
            ->groupBy('bin_id');

        $inventoryByBin = [];

        foreach ($bins as $bin) {
            $inventoryByBin[$bin->id] = $this->calculateCurrentInventory(
                $transactionsByBin->get($bin->id, new Collection())
            );
        }

        return $inventoryByBin;
    }

    protected function calculateCurrentInventory(Collection $transactions): int
    {
        $currentInventory = 0;

        foreach ($transactions as $transaction) {
            $quantity = (int) $transaction->quantity;

            switch ($transaction->transaction_type) {
                case 'count':
                    $currentInventory = $quantity;
                    break;
                case 'fill':
                case 'add':
                    $currentInventory += $quantity;
                    break;
                case 'waste':
                case 'remove':
                    $currentInventory -= $quantity;
                    break;
                case 'adjustment':
                    // TODO: Apply adjustment quantities once the app defines
                    // whether adjustment rows store signed deltas or separate
                    // increase/decrease semantics.
                    break;
            }
        }

        return max(0, $currentInventory);
    }
}
