<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignTraceIdMiddleware
{
    private const TRACE_ID_PATTERN = '/^[A-Za-z0-9-]{8,64}$/';

    public function handle(Request $request, Closure $next): Response
    {
        $traceId = $request->headers->get('X-Trace-Id');

        if (! is_string($traceId) || preg_match(self::TRACE_ID_PATTERN, $traceId) !== 1) {
            $traceId = (string) Str::uuid();
        }

        $request->attributes->set('trace_id', $traceId);

        Log::shareContext(['trace_id' => $traceId]);

        $response = $next($request);
        $response->headers->set('X-Trace-Id', $traceId);

        return $response;
    }
}
