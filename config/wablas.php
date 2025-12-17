<?php

return [
    // NOTE:
    // Aplikasi ini awalnya memakai Wablas. Sekarang mendukung wa-gateway yang menyediakan
    // Wablas-compatible API. Agar migrasi mulus, env WA_GATEWAY_* diprioritaskan dan tetap
    // kompatibel dengan env lama WABLAS_*.
    'base_url'   => env('WA_GATEWAY_BASE_URL', env('WABLAS_BASE_URL', 'https://solo.wablas.com')),
    'token'      => env('WA_GATEWAY_TOKEN', env('WABLAS_TOKEN', '')),
    'secret_key' => env('WA_GATEWAY_SECRET_KEY', env('WABLAS_SECRET_KEY', '')),

    // Master key wa-gateway (header/query `key`) jika di-set pada service wa-gateway.
    'key'        => env('WA_GATEWAY_KEY', env('WABLAS_KEY', '')),

    // Mapping opsional untuk beberapa grup WA (gunakan slug nama grup, misal: group_1, group_2)
    'group_ids'  => [
        'group_1' => env('WA_GATEWAY_GROUP_1_ID', env('WABLAS_GROUP_1_ID', '')),
        'group_2' => env('WA_GATEWAY_GROUP_2_ID', env('WABLAS_GROUP_2_ID', '')),
        'group_3' => env('WA_GATEWAY_GROUP_3_ID', env('WABLAS_GROUP_3_ID', '')),
    ],

    // Nomor yang tetap boleh memicu selesai TL (dipisah koma), contoh: "6281234,6285678"
    'finish_whitelist' => env('WA_GATEWAY_FINISH_WHITELIST', env('WABLAS_FINISH_WHITELIST', '')),

];
