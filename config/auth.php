<?php

use App\Models\SuperAdmin;
use App\Models\Teacher;
use App\Models\User;

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'super' => [
            'driver' => 'session',
            'provider' => 'super_admins',
        ],

        'teacher' => [
            'driver' => 'session',
            'provider' => 'teachers',
            'remember' => 60 * 24 * 365,
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],

        'super_admins' => [
            'driver' => 'eloquent',
            'model' => SuperAdmin::class,
        ],

        'teachers' => [
            'driver' => 'eloquent',
            'model' => Teacher::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
