<?php

return [
    'camat_jabatan_like' => array_filter(array_map(
        'trim',
        explode(',', (string) env('JADWAL_JABATAN_CAMAT', 'Camat Watumalang'))
    )),
    'sekcam_jabatan_like' => array_filter(array_map(
        'trim',
        explode(',', (string) env('JADWAL_JABATAN_SEKCAM', 'Sekretaris Kecamatan'))
    )),
];
