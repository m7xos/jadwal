<?php

return [
    'base_url'   => env('WABLAS_BASE_URL', 'https://solo.wablas.com'),
    'token'      => env('WABLAS_TOKEN', ''),
    'secret_key' => env('WABLAS_SECRET_KEY', ''),

    // Mapping opsional untuk beberapa grup WA (gunakan slug nama grup, misal: group_1, group_2)
    'group_ids'  => [
        'group_1' => env('WABLAS_GROUP_1_ID', ''),
        'group_2' => env('WABLAS_GROUP_2_ID', ''),
        'group_3' => env('WABLAS_GROUP_3_ID', ''),
    ],

    // Nomor yang tetap boleh memicu selesai TL (dipisah koma), contoh: "6281234,6285678"
    'finish_whitelist' => env('WABLAS_FINISH_WHITELIST', ''),

];
