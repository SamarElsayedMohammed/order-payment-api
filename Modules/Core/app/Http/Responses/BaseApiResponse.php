<?php

declare(strict_types=1);

namespace Modules\Core\Http\Responses;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class BaseApiResponse implements Responsable
{
    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $errors
     */
    public function __construct(
        protected mixed $data,
        protected string $message,
        protected string $code,
        protected int $httpStatus = 200,
        protected array $meta = [],
        protected array $errors = [],
    ) {}

    public function toResponse($request): JsonResponse
    {
        try {
            $payload = $this->formatData($request);
        } catch (\Throwable) {
            $payload = $this->data;
        }

        $body = [
            'success' => $this->httpStatus >= 200 && $this->httpStatus < 300,
            'trace_code' => $this->code,
            'code' => (string) $this->httpStatus,
            'message' => $this->message,
            'trace_id' => $request->attributes->get('trace_id'),
            'data' => $payload,
            'meta' => $this->meta === [] ? null : $this->meta,
        ];

        if ($this->errors !== []) {
            $body['errors'] = $this->errors;
        }

        return response()->json($body, $this->httpStatus);
    }

    abstract protected function formatData(Request $request): mixed;
}
