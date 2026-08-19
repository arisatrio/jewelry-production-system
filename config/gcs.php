<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Google Cloud Storage
    |--------------------------------------------------------------------------
    |
    | Upload gambar SPK ke bucket system-mahakarya (folder produksi),
    | sama seperti ERP_WHOJ.
    |
    */

    'project_id' => env('GOOGLE_CLOUD_PROJECT_ID', 'system-mahakarya'),

    'bucket' => env('GOOGLE_CLOUD_STORAGE_BUCKET', 'system-mahakarya'),

    'folder' => env('GOOGLE_CLOUD_STORAGE_FOLDER', 'produksi'),

    'credentials' => env(
        'GOOGLE_CLOUD_STORAGE_CREDENTIALS',
        'app/private/gcs-credentials.json',
    ),

];
