<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Contracts\View\View;
use Modules\Core\Enums\ErrorCode;

class ErrorCodeIndexController extends BaseController
{
    public function __invoke(): View
    {
        $codes = array_map(static fn (ErrorCode $code): array => [
            'code' => $code->value,
            'status' => $code->httpStatus(),
            'message' => $code->message(),
        ], ErrorCode::cases());

        return view('core::docs.error-codes', [
            'codes' => $codes,
        ]);
    }
}
