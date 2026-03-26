<?php

return [

    'paths' => ['api/*','dashboard', 'login', 'csrf-token'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:4200',
        'https://vmdevelopment.pfimegalife.co.id',
        'https://apps.pfimegalife.co.id'
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];