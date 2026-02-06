<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DisableCache
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Disable all caching
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', 'Sun, 28 Jan 1996 08:24:14 GMT');
        
        // Remove ETag to prevent 304 responses
        $response->headers->remove('ETag');
        
        // Set a random Last-Modified to prevent browser caching
        $response->headers->set('Last-Modified', gmdate('D, d M Y H:i:s', time() - rand(1, 60)) . ' GMT');

        return $response;
    }
}
