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
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(
            at: env('APP_ENV') === 'production'
                ? array_values(array_filter(explode(',', env('TRUSTED_PROXIES', '')))) : '*',
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
            'api.identity' => \App\Http\Middleware\EnsureApiIdentity::class,
        ]);
        $middleware->api(prepend: [\App\Http\Middleware\ApiResponseHeaders::class], append: [\App\Http\Middleware\RequirePrivateRepository::class]);
        $middleware->web(append: [\App\Http\Middleware\RequirePrivateRepository::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(fn (Request $request, \Throwable $e) => $request->is('api/*') || $request->expectsJson());
        $exceptions->render(function (\App\Exceptions\WorkflowException $exception, Request $request) {
            // Domain validation is returned as a user-correctable action, not a server error.
            if ($request->is('api/*') || $request->routeIs('evidences.*', 'loads.checklist', 'loads.signature-package', 'loads.signed-document', 'loads.close', 'loads.corrections', 'loads.reopen', 'calendar.import.*', 'loads.reschedule')) {
                return $request->expectsJson()
                    ? response()->json(['message' => $exception->getMessage()], 422)
                    : back()->withErrors(['workflow' => $exception->getMessage()]);
            }
            return null;
        });
    })->create();
