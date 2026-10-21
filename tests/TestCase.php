<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function tokenFor(User $user): string
    {
        $token = auth('api')->login($user);

        $this->assertIsString($token);

        $this->app['auth']->forgetGuards();

        return $token;
    }

    protected function asUser(User $user): static
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user));
    }
}
