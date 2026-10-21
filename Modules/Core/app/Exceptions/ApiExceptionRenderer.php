<?php

declare(strict_types=1);

namespace Modules\Core\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Core\Enums\ErrorCode;
use Modules\Core\Http\Responses\ErrorResponse;
use Modules\Core\Support\ErrorLogger;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Renders every exception on api/* as the standard envelope. Unexpected errors are reported
 * through ErrorLogger by the exception handler; this class only builds the response.
 */
final class ApiExceptionRenderer
{
    public function render(Throwable $exception, Request $request): Response
    {
        if ($request->attributes->get('trace_id') === null) {
            $request->attributes->set('trace_id', (string) Str::uuid());
        }

        if ($exception instanceof HttpResponseException) {
            return $exception->getResponse();
        }

        if ($exception instanceof ApiBusinessException) {
            ErrorLogger::logBusiness($exception->errorCode, $exception->getMessage(), $exception->context);

            return $this->respond($request, new ErrorResponse(
                errorCode: $exception->errorCode,
                message: $exception->getMessage(),
                errors: $exception->errors,
                data: $exception->data,
            ));
        }

        if ($exception instanceof ValidationException) {
            return $this->respond($request, new ErrorResponse(
                errorCode: ErrorCode::ValidationFailed,
                errors: $exception->errors(),
            ));
        }

        if ($exception instanceof TokenExpiredException) {
            return $this->respond($request, new ErrorResponse(ErrorCode::TokenExpired));
        }

        if ($exception instanceof AuthenticationException || $exception instanceof JWTException) {
            $code = $this->bearerTokenHasExpired($request) ? ErrorCode::TokenExpired : ErrorCode::Unauthenticated;

            return $this->respond($request, new ErrorResponse($code));
        }

        if ($exception instanceof AuthorizationException || $exception instanceof AccessDeniedHttpException) {
            return $this->respond($request, new ErrorResponse(ErrorCode::Forbidden));
        }

        if ($exception instanceof ModelNotFoundException || $exception instanceof NotFoundHttpException) {
            return $this->respond($request, new ErrorResponse(ErrorCode::NotFound));
        }

        if ($exception instanceof MethodNotAllowedHttpException) {
            return $this->respond($request, new ErrorResponse(ErrorCode::MethodNotAllowed), $exception->getHeaders());
        }

        if ($exception instanceof TooManyRequestsHttpException) {
            return $this->respond($request, new ErrorResponse(ErrorCode::TooManyRequests), $exception->getHeaders());
        }

        if ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() < 500) {
            return $this->respond(
                $request,
                new ErrorResponse(ErrorCode::HttpError, httpStatus: $exception->getStatusCode()),
                $exception->getHeaders(),
            );
        }

        $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;

        return $this->respond($request, new ErrorResponse(ErrorCode::InternalError, httpStatus: $status));
    }

    /**
     * @param  array<string, string|list<string>>  $headers
     */
    private function respond(Request $request, ErrorResponse $error, array $headers = []): Response
    {
        $response = $error->toResponse($request);
        $response->headers->add($headers);

        return $response;
    }

    private function bearerTokenHasExpired(Request $request): bool
    {
        $token = $request->bearerToken();

        if ($token === null || $token === '') {
            return false;
        }

        try {
            JWTAuth::setToken($token)->checkOrFail();
        } catch (TokenExpiredException) {
            return true;
        } catch (Throwable) {
            return false;
        }

        return false;
    }
}
