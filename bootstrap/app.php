<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Modules\Core\Enums\ErrorCode;
use Modules\Core\Exceptions\ApiBusinessException;
use Modules\Core\Exceptions\ApiExceptionRenderer;
use Modules\Core\Http\Middleware\AssignTraceIdMiddleware;
use Modules\Core\Http\Middleware\ForceJsonResponseMiddleware;
use Modules\Core\Support\ErrorLogger;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(ForceJsonResponseMiddleware::class);
        $middleware->append(AssignTraceIdMiddleware::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([ApiBusinessException::class, JWTException::class]);

        $exceptions->report(function (Throwable $exception): bool {
            $request = app()->runningInConsole() ? null : request();

            ErrorLogger::logException($exception, ErrorCode::InternalError, $request === null ? [] : [
                'path' => $request->path(),
                'method' => $request->method(),
                'input' => $request->all(),
            ]);

            return false;
        });

        $exceptions->shouldRenderJsonWhen(function (Request $request): bool {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return app(ApiExceptionRenderer::class)->render($exception, $request);
            }

            return null;
        });
    })->create();
