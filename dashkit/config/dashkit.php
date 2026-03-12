<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Package Branding
    |--------------------------------------------------------------------------
    */
    'name' => env('DASHKIT_NAME', 'Dashkit'),

    /*
    |--------------------------------------------------------------------------
    | Routing
    |--------------------------------------------------------------------------
    */
    'route_prefix' => env('DASHKIT_ROUTE_PREFIX', 'dashboard'),

    'route_middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */
    'auth' => [
        'enabled' => (bool) env('DASHKIT_AUTH_ENABLED', true),
        'guard' => env('DASHKIT_AUTH_GUARD', 'web'),
        'password_broker' => env('DASHKIT_AUTH_PASSWORD_BROKER', env('AUTH_PASSWORD_BROKER', 'users')),
        'login_route' => env('DASHKIT_LOGIN_PATH', 'login'),
        'logout_route' => env('DASHKIT_LOGOUT_PATH', 'logout'),
        'redirect_after_login' => env('DASHKIT_REDIRECT_AFTER_LOGIN', '/'),
    ],

    'mail' => [
        'from_address' => env('DASHKIT_MAIL_FROM_ADDRESS', env('MAIL_FROM_ADDRESS', 'hello@example.com')),
        'from_name' => env('DASHKIT_MAIL_FROM_NAME', env('MAIL_FROM_NAME', 'Dashkit')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sidebar Navigation
    |--------------------------------------------------------------------------
    */
    'sidebar' => [
        ['title' => 'Overview', 'route' => 'dashkit.home'],
        ['title' => 'Reports', 'route' => 'dashkit.page.reports'],
        ['title' => 'Settings', 'route' => 'dashkit.settings'],
    ],

    'topbar' => [
        'show_search' => true,
        'user_menu' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Widgets
    |--------------------------------------------------------------------------
    */
    'widgets' => [
        'enabled' => (bool) env('DASHKIT_WIDGETS_ENABLED', true),
        'defaults' => [
            [
                'key' => 'users',
                'title' => 'Users',
                'value' => 'users_count',
                'description' => 'Total registered users',
                'icon' => 'users',
                'format' => 'number',
                'order' => 10,
            ],
            [
                'key' => 'pages',
                'title' => 'Pages',
                'value' => 'pages_count',
                'description' => 'Generated dashboard pages',
                'icon' => 'pages',
                'format' => 'number',
                'order' => 20,
            ],
            [
                'key' => 'modules',
                'title' => 'Modules',
                'value' => 'modules_count',
                'description' => 'Generated dashboard modules',
                'icon' => 'modules',
                'format' => 'number',
                'order' => 30,
            ],
            [
                'key' => 'db',
                'title' => 'Database',
                'value' => 'db_connection',
                'description' => 'Active database connection',
                'icon' => 'database',
                'order' => 40,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Generated Pages
    |--------------------------------------------------------------------------
    */
    'generated_pages_namespace' => 'dashkit.pages',

    'generated_pages_path' => resource_path('views/dashkit/pages'),

    /*
    |--------------------------------------------------------------------------
    | Installer Publish Flags
    |--------------------------------------------------------------------------
    */
    'install' => [
        'publish_config' => (bool) env('DASHKIT_INSTALL_PUBLISH_CONFIG', true),
        'publish_views' => (bool) env('DASHKIT_INSTALL_PUBLISH_VIEWS', true),
        'publish_assets' => (bool) env('DASHKIT_INSTALL_PUBLISH_ASSETS', true),
        'append_routes' => (bool) env('DASHKIT_INSTALL_APPEND_ROUTES', true),
    ],
];