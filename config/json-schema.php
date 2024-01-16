<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    |
    | This is an absolute base URL where schemas will be accessible.
    |
    | (URL our local schemas must be relative to)
    |
    */

    'base_url' => env('APP_URL', null),

    /*
    |--------------------------------------------------------------------------
    | Schema base directory
    |--------------------------------------------------------------------------
    |
    | Where will all local schema files be stored?
    |
    */

    'local_base_prefix' => base_path('app/Schemas/'),

    /*
    |--------------------------------------------------------------------------
    | Schema base directory for tests
    |--------------------------------------------------------------------------
    |
    | Where will all local schema files be stored?
    |
    */

    'local_base_prefix_tests' => base_path('tests/schemas/'),

    /*
    |--------------------------------------------------------------------------
    | Storage disk name
    |--------------------------------------------------------------------------
    |
    | In order to generate schamas automatically, a storage disk needs to be created
    | that points to the same directory as `local_base_prefix`. This is the name
    | of that storage disk.
    |
    */

    'storage_disk_name' => 'schemas',
];
