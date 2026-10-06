<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Initial super admin
    |--------------------------------------------------------------------------
    |
    | Created by `php artisan db:seed` when both values are set. Change the
    | password after the first login and remove it from the environment.
    |
    */

    'seed_admin' => [
        'email' => env('SEED_ADMIN_EMAIL'),
        'password' => env('SEED_ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Login throttling
    |--------------------------------------------------------------------------
    |
    | Failed login attempts allowed per email + IP before a lockout, and the
    | lockout length in seconds. Shared by the web and API login.
    |
    */

    'login_max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),

    'login_decay_seconds' => (int) env('LOGIN_DECAY_SECONDS', 60),

];
