<?php

return [
    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The user model used by ProjectsBoard. This should be the fully qualified
    | class name of your User model.
    |
    */
    'user_model' => \App\Models\User::class,

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the external API endpoints.
    |
    */
    'api' => [
        // Enable or disable the external API
        'enabled' => env('PROJECTS_BOARD_API_ENABLED', true),

        // Rate limiting (requests per minute)
        'rate_limit' => env('PROJECTS_BOARD_API_RATE_LIMIT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Unsplash Integration
    |--------------------------------------------------------------------------
    |
    | Configuration for Unsplash image search integration.
    |
    */
    'unsplash' => [
        'access_key' => env('UNSPLASH_ACCESS_KEY'),
    ],
];
