<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user()?->fresh('role');
        if (!$user || $user->status !== 'ACTIVE' || !$user->role?->active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return $request->expectsJson()
                ? response()->json(['message' => 'La cuenta o su rol está inactivo.'], 403)
                : redirect()->route('login')->withErrors(['email' => 'La cuenta o su rol está inactivo.']);
        }

        Auth::setUser($user);
        return $next($request);
    }
}
