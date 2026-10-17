<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureApiIdentity
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();
        $user?->refresh()->load('role');
        if (!$user || !$token || $user->status !== 'ACTIVE' || !$user->role?->active
            || !$token->can('siget:api') || !$token->can('role:'.$user->role_id)) {
            $token?->delete();
            abort(401, 'La autorización ha vencido o el usuario ya no está habilitado.');
        }

        return $next($request);
    }
}
