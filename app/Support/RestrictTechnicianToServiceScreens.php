<?php

namespace App\Support;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictTechnicianToServiceScreens
{
    public function __construct(protected CurrentAccountMembershipResolver $membershipResolver)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $membership = $this->membershipResolver->forUser($request->user());

        if (! $membership || ! $membership->isTechnician()) {
            return $next($request);
        }

        if ($request->routeIs('services.*', 'locations.*', 'password.*', 'accounts.*', 'audit-log.*')) {
            return $next($request);
        }

        if (in_array($request->method(), ['GET', 'HEAD'], true)) {
            return redirect()->route('services.index');
        }

        abort(403);
    }
}
