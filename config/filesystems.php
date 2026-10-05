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

    /*
    |--------------------------------------------------------------------------
    | Private documents disk
    |--------------------------------------------------------------------------
    |
    | Invoice PDFs and payment receipts: never web-servable. "local" on a single
    | server; a PRIVATE object-storage bucket on hosts whose filesystem does
    | not survive a deploy (Laravel Cloud). Everything that reads or writes
    | those documents asks for this disk by name.
    |
    */

    'private_disk' => env('PRIVATE_DISK', 'local'),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        /*
         * Media uploaded by tests. It exists so the suite NEVER writes into —
         * or deletes from — storage/app/public, where the studio's real
         * photographs live.
         *
         * Without it the two share one folder, and because the testing
         * database's media ids restart at 1 after a refresh they collide with
         * real media ids: running the suite silently destroys uploaded
         * photographs. Wired up by MEDIA_DISK in phpunit.xml.
         */
        'media_testing' => [
            'driver' => 'local',
            'root' => storage_path('framework/testing/media'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        /*
         * The Vercel review copy (see DEPLOYMENT.md). Vercel's filesystem is
         * read-only except /tmp, so invoice PDFs and Livewire's temporary
         * uploads go here. api/index.php copies the bundled invoices in on a
         * cold start. /tmp lasts only as long as one server instance: fine for
         * a review link, never for the real site.
         */
        'tmp_private' => [
            'driver' => 'local',
            'root' => '/tmp/corememory/private',
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
    ],

];
