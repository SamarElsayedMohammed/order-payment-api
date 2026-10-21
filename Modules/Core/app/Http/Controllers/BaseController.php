<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Modules\Core\Enums\ErrorCode;
use Modules\Core\Exceptions\ApiBusinessException;

abstract class BaseController extends Controller
{
    use AuthorizesRequests;

    protected function currentUser(): User
    {
        $user = auth('api')->user();

        if (! $user instanceof User) {
            throw new ApiBusinessException(ErrorCode::Unauthenticated);
        }

        return $user;
    }

    protected function resolvePerPage(Request $request, int $default = 15, int $max = 100): int
    {
        $perPage = (int) $request->integer('per_page', $default);

        return min(max($perPage, 1), $max);
    }
}
