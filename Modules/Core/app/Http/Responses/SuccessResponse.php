<?php

declare(strict_types=1);

namespace Modules\Core\Http\Responses;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SuccessResponse extends BaseApiResponse
{
    protected function formatData(Request $request): mixed
    {
        if ($this->data instanceof JsonResource) {
            return $this->data->resolve($request);
        }

        return $this->data;
    }
}
