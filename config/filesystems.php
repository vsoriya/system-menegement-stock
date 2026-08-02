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
    | Product Image Disk
    |--------------------------------------------------------------------------
    |
    | Where uploaded product pictures live. The local "public" disk is right on
    | a single server, where the files sit next to the application and survive
    | restarts.
    |
    | Hosts that run the application in a container give it a throwaway disk, so
    | every deployment would wipe the pictures. On those, point this at "s3" and
    | fill in the AWS_ values below. Laravel Cloud's object storage and
    | Cloudflare R2 both speak the S3 protocol.
    |
    */

    'images' => env('IMAGE_DISK', 'public'),

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

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),

            // Root relative on purpose, so a product picture loads from
            // whatever host the page was served from. Building this from
            // APP_URL meant that opening the app on 127.0.0.1:8000 while
            // APP_URL said localhost produced image links pointing at a host
            // with nothing listening, and every picture silently broke.
            //
            // Set APP_STORAGE_URL to an absolute address only when images are
            // served from somewhere else, such as a CDN.
            // ?: rather than a default argument, because APP_STORAGE_URL= left
            // blank in .env reads as an empty string, not as absent, and would
            // otherwise produce links missing the /storage prefix entirely.
            'url' => rtrim(env('APP_STORAGE_URL') ?: '/storage', '/'),

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
            'visibility' => env('AWS_VISIBILITY', 'public'),
            'throw' => true,
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
    ],

];
