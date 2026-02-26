<?php

return [
    // In production, override LOG_CHANNEL with your online logging service channel.
    'default' => env('LOG_CHANNEL', 'docker'),

    'channels' => [
        // Writes to a file that docker-laravel-start.sh tails to Docker stderr.
        'docker' => [
            'driver' => 'single',
            'path'   => '/tmp/phpfpm-errors',
            'level'  => env('LOG_LEVEL', 'debug'),
        ],
    ],
];
