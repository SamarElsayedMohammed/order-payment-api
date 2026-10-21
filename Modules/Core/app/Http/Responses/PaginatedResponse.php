<?php

declare(strict_types=1);

namespace Modules\Core\Http\Responses;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaginatedResponse extends BaseApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        mixed $data,
        array $meta,
        string $message,
        string $code,
    ) {
        parent::__construct(
            data: $data,
            message: $message,
            code: $code,
            httpStatus: 200,
            meta: $meta,
        );
    }

    protected function formatData(Request $request): mixed
    {
        if ($this->data instanceof JsonResource) {
            return $this->data->resolve($request);
        }

        return $this->data;
    }
}
