<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

use App\Models\User;
use Modules\Core\Enums\ErrorCode;
use Modules\Core\Http\Responses\ErrorResponse;

class AuthService
{
    /**
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function register(array $data): string|ErrorResponse
    {
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $token = auth('api')->login($user);

        if (! is_string($token)) {
            return ErrorResponse::logged(ErrorCode::InternalError, 'Unable to issue an authentication token.');
        }

        return $token;
    }

    /**
     * @param  array{email: string, password: string}  $credentials
     */
    public function login(array $credentials): string|ErrorResponse
    {
        $token = auth('api')->attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ]);

        if (! is_string($token)) {
            return ErrorResponse::logged(ErrorCode::InvalidCredentials);
        }

        return $token;
    }

    public function refresh(): string|ErrorResponse
    {
        $token = auth('api')->refresh();

        if (! is_string($token)) {
            return ErrorResponse::logged(ErrorCode::Unauthenticated);
        }

        return $token;
    }

    public function logout(): void
    {
        auth('api')->logout();
    }
}
