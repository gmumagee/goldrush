<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(Request $request): View
    {
        $accounts = Account::query()
            ->withCount([
                'accountUsers as member_count' => fn ($query) => $query->where('status', 'active'),
            ])
            ->orderBy('account_name')
            ->orderBy('id')
            ->paginate(25);

        return view('admin.accounts.index', [
            'accounts' => $accounts,
        ]);
    }
}
