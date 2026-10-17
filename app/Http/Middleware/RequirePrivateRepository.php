<?php

namespace App\Http\Middleware;

use App\Services\InfrastructureReadinessService;
use Closure;
use Illuminate\Http\Request;

final class RequirePrivateRepository
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(app(InfrastructureReadinessService::class)->repositoryReady(), 503, 'El repositorio institucional está temporalmente fuera de servicio.');
        return $next($request);
    }
}
