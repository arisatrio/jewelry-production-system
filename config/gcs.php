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

    'inventory_chain_folder' => env('GOOGLE_CLOUD_STORAGE_CHAIN_FOLDER', 'inventory/chain'),

    'inventory_component_folder' => env('GOOGLE_CLOUD_STORAGE_COMPONENT_FOLDER', 'inventory/component'),

    /*
    | Path kredensial service account.
    | Relatif = di bawah storage/ (contoh: app/private/gcs-credentials.json
    | → storage/app/private/gcs-credentials.json). Absolute path juga didukung.
    */
    'credentials' => env(
        'GOOGLE_CLOUD_STORAGE_CREDENTIALS',
        'app/private/gcs-credentials.json',
    ),

];
