<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WebResponseHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        if (! $response->headers->has('Referrer-Policy')) {
            $response->headers->set('Referrer-Policy', 'same-origin');
        }

        if ($request->routeIs('home', 'docs', 'docs.v1', 'playground', 'downloads', 'status', 'security')) {
            $response->headers->set(
                'Content-Security-Policy',
                "default-src 'none'; script-src 'self'; style-src 'self'; worker-src blob:; connect-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'",
            );
        }

        if ($request->is('customer', 'customer/*', 'admin', 'admin/*')) {
            $response->headers->set('X-Robots-Tag', 'noindex');
        }

        return $response;
    }
}
