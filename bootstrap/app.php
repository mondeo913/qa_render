<?php

use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\NoCacheAuthenticated;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR |
                Request::HEADER_X_FORWARDED_HOST |
                Request::HEADER_X_FORWARDED_PROTO |
                Request::HEADER_X_FORWARDED_PORT |
                Request::HEADER_X_FORWARDED_PREFIX
        );
        $middleware->alias([
            'active-user' => \App\Http\Middleware\EnsureActiveUser::class,
            'permission' => EnsurePermission::class,
            'no-cache-auth' => NoCacheAuthenticated::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\App\Exceptions\WorkflowException $exception, Request $request) {
            // Domain validation is returned as a user-correctable action, not a server error.
            if ($request->routeIs('evidences.*', 'loads.checklist', 'loads.signature-package', 'loads.signed-document', 'loads.close', 'loads.corrections', 'loads.reopen', 'calendar.import.*', 'loads.reschedule')) {
                return $request->expectsJson()
                    ? response()->json(['message' => $exception->getMessage()], 422)
                    : back()->withErrors(['workflow' => $exception->getMessage()]);
            }
            return null;
        });
    })->create();
