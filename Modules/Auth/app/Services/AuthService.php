<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

use App\Models\User;
use Modules\Core\Enums\ErrorCode;
use Modules\Core\Exceptions\ApiBusinessException;

class AuthService
{
    /**
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function register(array $data): string
    {
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $token = auth('api')->login($user);

        if (! is_string($token)) {
            throw new ApiBusinessException(ErrorCode::InternalError, 'Unable to issue an authentication token.');
        }

        return $token;
    }

    /**
     * @param  array{email: string, password: string}  $credentials
     */
    public function login(array $credentials): string
    {
        $token = auth('api')->attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ]);

        if (! is_string($token)) {
            throw new ApiBusinessException(ErrorCode::InvalidCredentials);
        }

        return $token;
    }

    public function refresh(): string
    {
        $token = auth('api')->refresh();

        if (! is_string($token)) {
            throw new ApiBusinessException(ErrorCode::Unauthenticated);
        }

        return $token;
    }

    public function logout(): void
    {
        auth('api')->logout();
    }
}
