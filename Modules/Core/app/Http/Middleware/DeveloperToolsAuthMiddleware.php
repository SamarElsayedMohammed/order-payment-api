<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Support\DeveloperToolsAuthenticator;
use Symfony\Component\HttpFoundation\Response;

class DeveloperToolsAuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! DeveloperToolsAuthenticator::passes($request)) {
            return response('Authentication required.', 401, [
                'WWW-Authenticate' => 'Basic realm="Developer Tools"',
            ]);
        }

        return $next($request);
    }
}
