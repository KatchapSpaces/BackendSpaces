<?php

return [

    'paths' => [
        'api/*',
        'storage/*',
        'floorplan/*'
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:5174',
        'http://127.0.0.1:5174',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [
        'Content-Type',
        'Content-Disposition'
    ],

    'max_age' => 0,

    'supports_credentials' => false,
];
