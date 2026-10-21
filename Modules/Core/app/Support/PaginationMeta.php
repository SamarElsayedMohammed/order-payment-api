<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class PaginationMeta
{
    /**
     * @return array{pagination: array<string, mixed>}
     */
    public static function from(LengthAwarePaginator|CursorPaginator $paginator): array
    {
        if ($paginator instanceof CursorPaginator) {
            return [
                'pagination' => [
                    'mode' => 'cursor',
                    'per_page' => $paginator->perPage(),
                    'next_cursor' => $paginator->nextCursor()?->encode(),
                    'prev_cursor' => $paginator->previousCursor()?->encode(),
                    'has_more' => $paginator->hasMorePages(),
                ],
            ];
        }

        return [
            'pagination' => [
                'mode' => 'offset',
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }
}
