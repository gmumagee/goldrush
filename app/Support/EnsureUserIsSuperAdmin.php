<?php

namespace App\Support;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(Demo::isEnabled(), 404);

        $user = $request->user();

        abort_unless($user?->isSuperAdmin(), 403);

        return $next($request);
    }
}
