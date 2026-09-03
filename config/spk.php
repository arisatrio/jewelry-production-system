<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Form Document Control
    |--------------------------------------------------------------------------
    |
    | Metadata header untuk dokumen SPK (ISO document control).
    | issue_date: format d-m-Y. null = tanggal hari ini saat cetak.
    |
    */

    'form_document_no' => 'WHOJ-PRD-FRM-001',

    'jewelcad_form_document_no' => 'WHOJ-PRD-FRM-002',

    'resin_form_document_no' => 'WHOJ-PRD-FRM-003',

    'coran_form_document_no' => 'WHOJ-PRD-FRM-004',

    'resin_detail_statuses' => [
        ['value' => 'OK', 'label' => 'OK'],
        ['value' => 'NOT OK', 'label' => 'NOT OK'],
    ],

    'coran_detail_statuses' => [
        ['value' => 'OK', 'label' => 'OK'],
        ['value' => 'NOK', 'label' => 'Not OK'],
    ],

    'company_name' => 'Wanda House of Jewels',

    'form_title' => 'Form SPK',

    'issue_no' => '01',

    'revision' => '03',

    'issue_date' => '14/08/2026',

    'logo' => 'images/logo.jpg',

    /*
    |--------------------------------------------------------------------------
    | Production Image Base URL
    |--------------------------------------------------------------------------
    |
    | Base URL GCS untuk file gambar SPK (kolom file_name di tabel spk).
    |
    */

    'production_image_base_url' => env(
        'SPK_PRODUCTION_IMAGE_BASE_URL',
        'https://storage.googleapis.com/system-mahakarya/produksi/',
    ),

    /*
    |--------------------------------------------------------------------------
    | Print QR Code URL
    |--------------------------------------------------------------------------
    |
    | Override URL yang di-encode ke QR pada cetak SPK.
    | Isi URL absolut untuk sementara (mis. landing list SPK).
    | Kosongkan (SPK_PRINT_QR_URL=) agar kembali ke URL dinamis detail SPK.
    |
    */

    'print_qr_url' => env(
        'SPK_PRINT_QR_URL',
        'https://production.mahakarya.online/user/spk',
    ),

    /*
    |--------------------------------------------------------------------------
    | Approval Workflow
    |--------------------------------------------------------------------------
    |
    | Draft (status kosong) → SPV Kirim (SPK010) → Manager Approve (SPKDONE).
    | Authorization memakai permission mahakarya (spk.* via role_id).
    | guest_permissions dipakai saat user belum login (kompatibilitas form).
    |
    */

    'approval' => [
        'pending_status' => 'SPK010',
        'done_status' => 'SPKDONE',
        'doc_name' => 'spk',
        'guest_permissions' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env(
                'SPK_APPROVAL_GUEST_PERMISSIONS',
                'spk.view,spk.create,spk.edit_draft',
            )),
        ))),
    ],

];
