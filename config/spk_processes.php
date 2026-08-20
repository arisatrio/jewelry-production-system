<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SPK Production Process → Database Tables
    |--------------------------------------------------------------------------
    |
    | Maps each process tab to third-connection tables that store process data
    | via spk_id (Production.row_id).
    |
    */

    'tabs' => [
        [
            'key' => 'JewelCAD',
            'label' => 'JewelCAD',
            'tables' => ['requestjwcaddetails'],
            'parent' => [
                'requestjwcaddetails' => [
                    'table' => 'requestjwcad',
                    'local_key' => 'row_id',
                    'owner_key' => 'row_id',
                    'fields' => [
                        'doc_no' => 'doc_no',
                        'tanggal' => 'trans_date',
                    ],
                ],
            ],
        ],
        [
            'key' => 'Resin',
            'label' => 'Resin',
            'tables' => ['resin'],
        ],
        [
            'key' => 'Coran',
            'label' => 'Coran',
            'tables' => ['coranspk'],
            'parent' => [
                'coranspk' => [
                    'table' => 'coran',
                    'local_key' => 'row_id',
                    'owner_key' => 'row_id',
                    'fields' => [
                        'doc_no' => 'doc_no',
                        'tanggal' => 'trans_date',
                    ],
                ],
            ],
        ],
        [
            'key' => 'Finishing',
            'label' => 'Finishing',
            'tables' => ['finishinghandmade'],
        ],
        [
            'key' => 'Poles Rangka',
            'label' => 'Poles Rangka',
            'tables' => ['polishframe'],
        ],
        [
            'key' => 'Pasang Batu',
            'label' => 'Pasang Batu',
            'tables' => ['diamondmounting', 'diamondunload'],
        ],
        [
            'key' => 'Poles Chrome',
            'label' => 'Poles Chrome',
            'tables' => ['polishfinishedgood'],
        ],
        // [
        //     'key' => 'Pengerjaan Lanjutan',
        //     'label' => 'Pengerjaan Lanjutan',
        //     'tables' => ['grafir'],
        //     'placement' => 'main',
        // ],
        // [
        //     'key' => 'Modifikasi Barang Jadi',
        //     'label' => 'Modifikasi Barang Jadi',
        //     'tables' => [],
        //     'placement' => 'main',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Shared material / transaction tables (spk_id)
    |--------------------------------------------------------------------------
    */

    'shared_tables' => [
        'trmaterialgold',
        'trstone',
        'trchain',
    ],

    /*
    |--------------------------------------------------------------------------
    | Shrink / craftsman report sources for tab Laporan
    |--------------------------------------------------------------------------
    */

    'shrink_sources' => [
        [
            'table' => 'finishinghandmade',
            'label' => 'Finishing / Handmade',
            'shrink_column' => 'shrink',
            'date_column' => 'send_craftsman_date',
        ],
        [
            'table' => 'polishframe',
            'label' => 'Poles Rangka',
            'shrink_column' => 'shrink',
            'date_column' => 'send_craftsman_date',
        ],
        [
            'table' => 'diamondmounting',
            'label' => 'Pasang Batu',
            'shrink_column' => 'computed_mounting',
            'date_column' => 'send_craftsman_date',
        ],
        [
            'table' => 'polishfinishedgood',
            'label' => 'Poles Barang Jadi',
            'shrink_column' => 'shrink',
            'date_column' => 'send_craftsman_date',
        ],
        [
            'table' => 'grafir',
            'label' => 'Grafir',
            'shrink_column' => 'shrink',
            'date_column' => 'date_to',
        ],
    ],

];
