<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NoCacheAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Laravel 12 may return StreamedResponse for file downloads. Symfony
        // responses expose headers through the HeaderBag; the Laravel
        // Response::header() helper is not available on StreamedResponse.
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
