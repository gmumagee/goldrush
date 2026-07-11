<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    protected function currentAccountId(Request $request): int
    {
        return (int) $request->session()->get('current_account_id');
    }
}
