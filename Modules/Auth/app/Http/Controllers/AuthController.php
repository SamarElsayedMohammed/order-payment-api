<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use Modules\Auth\Http\Requests\LoginRequest;
use Modules\Auth\Http\Requests\RegisterRequest;
use Modules\Auth\Http\Resources\UserResource;
use Modules\Auth\Http\Responses\AuthTokenResponse;
use Modules\Auth\Http\Responses\UserProfileResponse;
use Modules\Auth\Services\AuthService;
use Modules\Core\Http\Controllers\BaseController;
use Modules\Core\Http\Responses\ErrorResponse;
use Modules\Core\Http\Responses\SuccessResponse;
use Modules\Core\Support\SuccessCode;

class AuthController extends BaseController
{
    public function __construct(private readonly AuthService $auth) {}

    /**
     * Register a user and return a JWT.
     *
     * Possible errors: AUTH-REG-001.
     */
    public function register(RegisterRequest $request): AuthTokenResponse|ErrorResponse
    {
        $result = $this->auth->register($request->validated());

        if ($result instanceof ErrorResponse) {
            return $result;
        }

        return new AuthTokenResponse(
            token: $result,
            message: 'Account created successfully.',
            code: SuccessCode::REGISTERED,
            httpStatus: 201,
        );
    }

    /**
     * Exchange email and password for a JWT. Limited to 5 attempts per minute.
     *
     * Possible errors: AUTH-LGN-001, AUTH-LGN-002, SYS-GEN-004.
     */
    public function login(LoginRequest $request): AuthTokenResponse|ErrorResponse
    {
        $result = $this->auth->login($request->validated());

        if ($result instanceof ErrorResponse) {
            return $result;
        }

        return new AuthTokenResponse(
            token: $result,
            message: 'Logged in successfully.',
            code: SuccessCode::LOGGED_IN,
        );
    }

    /**
     * Return the authenticated user.
     *
     * Possible errors: AUTH-TKN-001.
     */
    public function me(): UserProfileResponse
    {
        return new UserProfileResponse(new UserResource($this->currentUser()));
    }

    /**
     * Issue a fresh JWT and invalidate the current one.
     *
     * Possible errors: AUTH-TKN-001, AUTH-TKN-002.
     */
    public function refresh(): AuthTokenResponse|ErrorResponse
    {
        $result = $this->auth->refresh();

        if ($result instanceof ErrorResponse) {
            return $result;
        }

        return new AuthTokenResponse(
            token: $result,
            message: 'Token refreshed successfully.',
            code: SuccessCode::REFRESHED,
        );
    }

    /**
     * Invalidate the current JWT.
     *
     * Possible errors: AUTH-TKN-001.
     */
    public function logout(): SuccessResponse
    {
        $this->auth->logout();

        return new SuccessResponse(
            data: null,
            message: 'Logged out successfully.',
            code: SuccessCode::LOGGED_OUT,
        );
    }
}
