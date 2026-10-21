<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Modules\Core\Enums\ErrorCode;
use Modules\Core\Http\Responses\ErrorResponse;

abstract class BaseController extends Controller
{
    use AuthorizesRequests;

    protected function currentUser(): User
    {
        /** @var User $user */
        $user = auth('api')->user();

        return $user;
    }

    /**
     * @param  array<string, mixed>  $errors
     * @param  array<string, mixed>  $context
     */
    protected function errorResponse(
        ErrorCode $errorCode,
        ?string $message = null,
        array $errors = [],
        mixed $data = null,
        array $context = [],
    ): ErrorResponse {
        return ErrorResponse::logged(
            errorCode: $errorCode,
            message: $message,
            errors: $errors,
            data: $data,
            context: $context,
        );
    }

    protected function resolvePerPage(Request $request, int $default = 15, int $max = 100): int
    {
        $perPage = (int) $request->integer('per_page', $default);

        return min(max($perPage, 1), $max);
    }
}
