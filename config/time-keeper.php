<?php

return [
    'timezone' => 'Europe/Brussels',

    'admin' => [
        'name' => env('TIME_KEEPER_ADMIN_NAME', 'Admin'),
        'email' => env('TIME_KEEPER_ADMIN_EMAIL', 'admin@example.com'),
        'password' => env('TIME_KEEPER_ADMIN_PASSWORD', 'password'),
    ],

    'invitation_expires_in_days' => 7,
];
