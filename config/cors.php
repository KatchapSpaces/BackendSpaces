<?php

return [

    'paths' => [
        'api/*',
        'storage/*',
        'floorplan/*'
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_headers' => ['*'],

    'exposed_headers' => [
        'Content-Type',
        'Content-Disposition'
    ],

    'max_age' => 0,

    'supports_credentials' => false,
];
