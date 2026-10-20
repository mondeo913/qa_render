<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        // El Enlace Institucional no administra plantillas, aunque una instalación
        // anterior o una asignación manual todavía tenga el permiso en la base.
        abort_if(
            $permission === 'templates.manage' && $user?->role?->code === 'ENLACE_INSTITUCIONAL',
            403,
            'Este perfil no administra plantillas.'
        );
        abort_unless($user && $user->hasPermission($permission), 403, 'No cuenta con el permiso requerido.');
        return $next($request);
    }
}