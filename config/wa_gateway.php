<?php

return [
    // NOTE:
    // Aplikasi ini memakai wa-gateway.
    //
    // Provider:
    // - wa-gateway: menganggap group id perlu format JID (contoh: 1203...@g.us)
    // - legacy: memakai format group id numerik
    'provider'   => env('WA_PROVIDER', 'wa-gateway'),
    'base_url'   => env('WA_GATEWAY_BASE_URL', 'http://localhost:5001'),
    'token'      => env('WA_GATEWAY_TOKEN', ''),
    'secret_key' => env('WA_GATEWAY_SECRET_KEY', ''),
    // Optional: path/URL to wa-gateway device registry (for token sync)
    'registry_path' => env('WA_GATEWAY_REGISTRY_PATH', '/home/wa-gateway/wa_credentials/device-registry.json'),
    'registry_url' => env('WA_GATEWAY_REGISTRY_URL', ''),
    'session_id' => env('WA_GATEWAY_SESSION_ID', ''),
    'registry_token' => env('WA_GATEWAY_REGISTRY_TOKEN', ''),
    'registry_user' => env('WA_GATEWAY_REGISTRY_USER', ''),
    'registry_pass' => env('WA_GATEWAY_REGISTRY_PASS', ''),

    // Master key wa-gateway (header/query `key`) jika di-set pada service wa-gateway.
    'key'        => env('WA_GATEWAY_KEY', ''),

    // Mapping opsional untuk beberapa grup WA (gunakan slug nama grup, misal: group_1, group_2)
    'group_ids'  => [
        'group_1' => env('WA_GATEWAY_GROUP_1_ID', ''),
        'group_2' => env('WA_GATEWAY_GROUP_2_ID', ''),
        'group_3' => env('WA_GATEWAY_GROUP_3_ID', ''),
    ],

    // Nomor yang tetap boleh memicu selesai TL (dipisah koma), contoh: "6281234,6285678"
    'finish_whitelist' => env('WA_GATEWAY_FINISH_WHITELIST', ''),

];
