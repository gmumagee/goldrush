<?php

namespace App\Support;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCurrentAccountSelected
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->has('current_account_id')) {
            return $next($request);
        }

        if (Tenancy::isSingle()) {
            Tenancy::pinSingleAccountInSession($request);

            return $next($request);
        }

        return redirect()->route('accounts.select');
    }
}
