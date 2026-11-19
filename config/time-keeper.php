<?php

return [
    'timezone' => 'Europe/Brussels',

    'user' => [
        'name' => env('TIME_KEEPER_USER_NAME', 'Admin'),
        'email' => env('TIME_KEEPER_USER_EMAIL', 'admin@example.com'),
        'password' => env('TIME_KEEPER_USER_PASSWORD', 'password'),
    ],
];
