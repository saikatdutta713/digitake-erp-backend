<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CorrelationIdMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = $request->header('X-Correlation-ID');

        if (! $correlationId || ! is_string($correlationId)) {
            $correlationId = Str::uuid()->toString();
            $request->headers->set('X-Correlation-ID', $correlationId);
        }

        $request->attributes->set('correlation_id', $correlationId);

        $response = $next($request);

        $response->headers->set('X-Correlation-ID', $correlationId);

        return $response;
    }
}
