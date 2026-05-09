<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | PHPSockets Server
    |--------------------------------------------------------------------------
    |
    | These values are used by the Laravel Artisan commands and by the
    | PhpSocketsManager when building a ChatServer instance.
    |
    */

    'server' => [
        'host' => env('PHPSOCKETS_HOST', '127.0.0.1'),
        'port' => (int) env('PHPSOCKETS_PORT', 8080),
        'max_payload_bytes' => (int) env('PHPSOCKETS_MAX_PAYLOAD_BYTES', 4 * 1024 * 1024),
        'tick_microseconds' => (int) env('PHPSOCKETS_TICK_MICROSECONDS', 10000),
        'connection_limit' => (int) env('PHPSOCKETS_CONNECTION_LIMIT', 100),
        'debug' => (bool) env('PHPSOCKETS_DEBUG', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | PHPSockets Chat
    |--------------------------------------------------------------------------
    */

    'chat' => [
        'max_display_name_length' => (int) env('PHPSOCKETS_MAX_DISPLAY_NAME_LENGTH', 40),
        'max_room_name_length' => (int) env('PHPSOCKETS_MAX_ROOM_NAME_LENGTH', 80),
        'max_private_group_members' => (int) env('PHPSOCKETS_MAX_PRIVATE_GROUP_MEMBERS', 20),
        'allow_guest_sessions' => (bool) env('PHPSOCKETS_ALLOW_GUEST_SESSIONS', true),
        'history_limit' => (int) env('PHPSOCKETS_HISTORY_LIMIT', 50),
        'max_attachment_bytes' => (int) env('PHPSOCKETS_MAX_ATTACHMENT_BYTES', 2 * 1024 * 1024),
        'max_attachment_file_name_length' => (int) env('PHPSOCKETS_MAX_ATTACHMENT_FILE_NAME_LENGTH', 180),

        'allowed_attachment_mime_types' => [
            'image/png',
            'image/jpeg',
            'image/gif',
            'application/pdf',
            'text/plain',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | PHPSockets Storage
    |--------------------------------------------------------------------------
    |
    | memory:
    |   Default runtime storage.
    |
    | sqlite/mysql/pgsql:
    |   Used by migrations and future persistent Laravel examples.
    |
    */

    'storage' => [
        'driver' => env('PHPSOCKETS_STORAGE', 'memory'),

        'database' => env('PHPSOCKETS_DATABASE', database_path('phpsockets.sqlite')),

        'dsn' => env('PHPSOCKETS_DSN'),

        'username' => env('PHPSOCKETS_DB_USERNAME'),

        'password' => env('PHPSOCKETS_DB_PASSWORD'),
    ],
];
