<?php

declare(strict_types=1);

return [
    'channels' => [
        'payments' => [
            'driver' => 'daily',
            'path' => storage_path('logs/payments.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => env('LOG_DAILY_DAYS', 14),
            'replace_placeholders' => true,
        ],
    ],
];
