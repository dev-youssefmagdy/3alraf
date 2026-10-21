<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        // Payment proofs: never web-served; downloaded only through authorized controller routes.
        'private' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'visibility' => 'private',
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Public media (CDN origin). MEDIA_DRIVER=local serves a directory through a static-only vhost; =s3 uses any S3-compatible store (R2/MinIO/S3).
        'media' => env('MEDIA_DRIVER', 'local') === 's3' ? [
            'driver' => 's3',
            'key' => env('MEDIA_KEY', env('AWS_ACCESS_KEY_ID')),
            'secret' => env('MEDIA_SECRET', env('AWS_SECRET_ACCESS_KEY')),
            'region' => env('MEDIA_REGION', env('AWS_DEFAULT_REGION', 'auto')),
            'bucket' => env('MEDIA_BUCKET', env('AWS_BUCKET')),
            'endpoint' => env('MEDIA_ENDPOINT', env('AWS_ENDPOINT')),
            'use_path_style_endpoint' => (bool) env('MEDIA_PATH_STYLE', false),
            'url' => env('MEDIA_URL'),
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ] : [
            'driver' => 'local',
            'root' => env('MEDIA_ROOT', storage_path('app/media')),
            'url' => env('MEDIA_URL', rtrim(env('APP_URL', 'http://localhost'), '/').'/media'),
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
        public_path('media') => storage_path('app/media'),
    ],

];
