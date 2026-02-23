<?php

return [
    'managers' => [
        'default' => [
            'dev'        => env('APP_DEBUG', false),
            'meta'       => 'attributes',
            'connection' => 'mysql',
            'namespaces' => [],
            'paths'      => [app_path('Entities')],
            'repository' => Doctrine\ORM\EntityRepository::class,
            'proxies'    => [
                'namespace'     => 'App\\Proxies',
                'path'          => storage_path('proxies'),
                'auto_generate' => env('DOCTRINE_PROXY_AUTOGENERATE', false),
            ],
            'events'     => [
                'listeners'   => [],
                'subscribers' => [],
            ],
            'filters'       => [],
            'mapping_types' => [],
        ],
    ],

    'extensions' => [],

    'custom_types' => [],

    'custom_datetime_functions' => [],
    'custom_numeric_functions'  => [],
    'custom_string_functions'   => [],

    'cache' => [
        'second_level' => false,
        'default'      => 'array',
        'metadata'     => ['driver' => 'array'],
        'query'        => ['driver' => 'array'],
        'result'       => ['driver' => 'array'],
    ],

    'gedmo' => [
        'all_mappings' => false,
    ],
];
