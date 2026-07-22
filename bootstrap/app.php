<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Support\EnsureActiveCurrentAccountMember;
use App\Support\EnsureCurrentAccountSelected;
use App\Support\EnsureUserIsSuperAdmin;
use App\Support\RestrictTechnicianToServiceScreens;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'account.selected' => EnsureCurrentAccountSelected::class,
            'account.member' => EnsureActiveCurrentAccountMember::class,
            'super.admin' => EnsureUserIsSuperAdmin::class,
            'technician.services' => RestrictTechnicianToServiceScreens::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
