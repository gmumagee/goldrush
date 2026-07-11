<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Bin;
use App\Models\Location;
use App\Models\Machine;
use App\Models\Product;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\VendingRoute;
use App\Models\Vendor;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $accountId = (int) $request->session()->get('current_account_id');
        $account = Account::find($accountId);

        return view('dashboard', [
            'account' => $account,
            'metrics' => $this->metrics($accountId),
            'recentTransactions' => $this->recentTransactions($accountId),
        ]);
    }

    protected function metrics(int $accountId): array
    {
        return [
            ['label' => 'Machines', 'value' => $this->safeCount(Machine::query()->where('account_id', $accountId))],
            ['label' => 'Locations', 'value' => $this->safeCount(Location::query()->where('account_id', $accountId))],
            ['label' => 'Products', 'value' => $this->safeCount(Product::query()->where('account_id', $accountId))],
            ['label' => 'Warehouses', 'value' => $this->safeCount(Warehouse::query()->where('account_id', $accountId))],
            ['label' => 'Bins', 'value' => $this->safeCount(Bin::query()->where('account_id', $accountId))],
            ['label' => 'Services', 'value' => $this->safeCount(Service::query()->where('account_id', $accountId))],
            ['label' => 'Transactions', 'value' => $this->safeCount(Transaction::query()->where('account_id', $accountId))],
            ['label' => 'Vendors', 'value' => $this->safeCount(Vendor::query()->where('account_id', $accountId))],
            ['label' => 'Routes', 'value' => $this->safeCount(VendingRoute::query()->where('account_id', $accountId))],
        ];
    }

    protected function recentTransactions(int $accountId): Collection
    {
        try {
            return Transaction::query()
                ->where('account_id', $accountId)
                ->with(['product', 'machine', 'service'])
                ->latest('id')
                ->limit(8)
                ->get();
        } catch (\Throwable) {
            return collect();
        }
    }

    protected function safeCount(Builder $query): int|string
    {
        try {
            return $query->count();
        } catch (\Throwable) {
            return '—';
        }
    }
}
