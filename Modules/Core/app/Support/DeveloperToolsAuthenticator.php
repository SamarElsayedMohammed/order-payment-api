<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use Illuminate\Http\Request;

final class DeveloperToolsAuthenticator
{
    public static function passes(Request $request): bool
    {
        $username = (string) config('developer_tools.username', '');
        $password = (string) config('developer_tools.password', '');

        if ($username === '' || $password === '') {
            return false;
        }

        $givenUser = (string) $request->getUser();
        $givenPassword = (string) $request->getPassword();

        return hash_equals($username, $givenUser) && hash_equals($password, $givenPassword);
    }
}
