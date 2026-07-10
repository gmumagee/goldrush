<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountSelected
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has('current_account_id')) {
            return redirect()->route('accounts.select');
        }

        return $next($request);
    }
}
