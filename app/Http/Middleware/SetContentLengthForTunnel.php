<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetContentLengthForTunnel
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! app()->environment('local') || ! str_ends_with($request->getHost(), '.devtunnels.ms')) {
            return $response;
        }

        $content = $response->getContent();

        if (is_string($content)
            && str_starts_with(strtolower($response->headers->get('Content-Type', '')), 'text/html')
            && ! $request->isMethod('HEAD')
            && ! $response->isEmpty()
            && ! $response->isInformational()
            && ! $response->headers->has('Content-Encoding')
            && ! $response->headers->has('Transfer-Encoding')) {
            // Let the tunnel finish buffered HTML so deferred scripts can execute.
            $response->headers->set('Content-Length', (string) strlen($content));
        }

        return $response;
    }
}
