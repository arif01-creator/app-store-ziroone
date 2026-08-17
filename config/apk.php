<?php

return [

    /*
    |--------------------------------------------------------------------------
    | APK Disk
    |--------------------------------------------------------------------------
    |
    | Filesystem disk used to hold APK binaries. This MUST be a private disk —
    | APKs are only ever served through the gated download controller, never as
    | a public URL. Swap to "s3" (and configure the disk in filesystems.php) to
    | move storage off the local box; no business logic needs to change.
    |
    */

    'disk' => env('APK_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Storage Directory
    |--------------------------------------------------------------------------
    |
    | Root directory on the disk above. Files land at:
    |   {directory}/{app_id}/{version_code}.apk
    |
    */

    'directory' => 'apks',

    /*
    |--------------------------------------------------------------------------
    | Maximum Upload Size (kilobytes)
    |--------------------------------------------------------------------------
    |
    | Enforced by the upload form request. PHP's own upload_max_filesize and
    | post_max_size must be at least this large or the request never reaches
    | Laravel — see the README for the php.ini values to set.
    |
    */

    'max_upload_kb' => (int) env('APK_MAX_UPLOAD_KB', 204800),

];
