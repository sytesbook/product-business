<?php

use Monolog\Handler\StreamHandler;

return [
    'default' => env('LOG_CHANNEL', 'stderr'),

    'channels' => [
        'stderr' => [
            'driver'  => 'monolog',
            'level'   => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'with'    => [
                'stream' => 'php://stderr',
            ],
        ],
    ],
];
