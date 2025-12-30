<?php

namespace App\Support;

class WaMessageTemplateDefaults
{
    /**
     * @return array<string, array{label: string, description: string, placeholders: array<int, string>, template: string}>
     */
    public static function definitions(): array
    {
        $commonPlaceholders = [
            'personil_block',
        ];

        $definitions = [
            'agenda_group' => [
                'label' => 'Agenda ke Grup',
                'description' => 'Pesan agenda untuk grup WhatsApp (single agenda).',
                'placeholders' => [
                    'tanggal_header',
                    'judul',
                    'waktu',
                    'tempat',
                    'peserta_line',
                    'keterangan_line',
                    'surat_line',
                    'lampiran_line',
                    'footer',
                ],
                'required_placeholders' => [
                    'tanggal_header',
                    'judul',
                    'waktu',
                    'tempat',
                    'footer',
                ],
                'sections' => [
                    'header' => [
                        'label' => 'Header',
                        'template' => '📌 REKAP AGENDA — {tanggal_header}',
                    ],
                    'body' => [
                        'label' => 'Agenda',
                        'template' => implode("\n", [
                            '#1 {judul}',
                            '   ⏰ {waktu} | 📍 {tempat}',
                            '{peserta_line}{keterangan_line}{surat_line}{lampiran_line}',
                        ]),
                    ],
                    'footer' => [
                        'label' => 'Footer',
                        'template' => '{footer}',
                    ],
                ],
                'template' => implode("\n\n", [
                    '📌 REKAP AGENDA — {tanggal_header}',
                    implode("\n", [
                        '#1 {judul}',
                        '   ⏰ {waktu} | 📍 {tempat}',
                        '{peserta_line}{keterangan_line}{surat_line}{lampiran_line}',
                    ]),
                    '{footer}',
                ]),
            ],
            'agenda_personil' => [
                'label' => 'Agenda ke Personil',
                'description' => 'Pesan undangan agenda ke WA personil.',
                'placeholders' => [
                    'nama_kegiatan',
                    'nomor_surat',
                    'tanggal',
                    'waktu',
                    'tempat',
                    'keterangan_block',
                    'surat_block',
                    'lampiran_block',
                    'footer',
                ],
                'required_placeholders' => [
                    'nama_kegiatan',
                    'nomor_surat',
                    'tanggal',
                    'waktu',
                    'tempat',
                    'footer',
                ],
                'sections' => [
                    'header' => [
                        'label' => 'Header',
                        'template' => implode("\n", [
                            '*UNDANGAN / INFORMASI KEGIATAN*',
                            '────────────────────────',
                        ]),
                    ],
                    'body' => [
                        'label' => 'Isi Agenda',
                        'template' => implode("\n", [
                            '*Nama Kegiatan*',
                            '*{nama_kegiatan}*',
                            '',
                            '*Nomor Surat*',
                            '*{nomor_surat}*',
                            '',
                            '*Hari / Tanggal*',
                            '{tanggal}',
                            '',
                            '*Waktu*',
                            '{waktu}',
                            '',
                            '*Tempat*',
                            '{tempat}',
                            '',
                            '{keterangan_block}{surat_block}{lampiran_block}Mohon kehadiran Bapak/Ibu sesuai jadwal di atas.',
                        ]),
                    ],
                    'footer' => [
                        'label' => 'Footer',
                        'template' => '{footer}',
                    ],
                ],
                'template' => implode("\n\n", [
                    implode("\n", [
                        '*UNDANGAN / INFORMASI KEGIATAN*',
                        '────────────────────────',
                    ]),
                    implode("\n", [
                        '*Nama Kegiatan*',
                        '*{nama_kegiatan}*',
                        '',
                        '*Nomor Surat*',
                        '*{nomor_surat}*',
                        '',
                        '*Hari / Tanggal*',
                        '{tanggal}',
                        '',
                        '*Waktu*',
                        '{waktu}',
                        '',
                        '*Tempat*',
                        '{tempat}',
                        '',
                        '{keterangan_block}{surat_block}{lampiran_block}Mohon kehadiran Bapak/Ibu sesuai jadwal di atas.',
                    ]),
                    '{footer}',
                ]),
            ],
            'tindak_lanjut_reminder' => [
                'label' => 'Pengingat Tindak Lanjut',
                'description' => 'Pesan pengingat batas waktu tindak lanjut.',
                'placeholders' => [
                    'nomor_surat',
                    'kode_tl',
                    'label_lines',
                    'surat_block',
                    'lampiran_block',
                    'disposisi_block',
                    'balasan_line',
                    'footer',
                ],
                'required_placeholders' => [
                    'nomor_surat',
                    'label_lines',
                    'balasan_line',
                    'footer',
                ],
                'sections' => [
                    'header' => [
                        'label' => 'Header',
                        'template' => '*Pengingat TL Surat Nomor: {nomor_surat}*',
                    ],
                    'body' => [
                        'label' => 'Isi Pengingat',
                        'template' => implode("\n", [
                            '{label_lines}',
                            '',
                            '{surat_block}{lampiran_block}{disposisi_block}{balasan_line}',
                        ]),
                    ],
                    'footer' => [
                        'label' => 'Footer',
                        'template' => '{footer}',
                    ],
                ],
                'template' => implode("\n\n", [
                    '*Pengingat TL Surat Nomor: {nomor_surat}*',
                    implode("\n", [
                        '{label_lines}',
                        '',
                        '{surat_block}{lampiran_block}{disposisi_block}{balasan_line}',
                    ]),
                    '{footer}',
                ]),
            ],
            'group_rekap' => [
                'label' => 'Rekap Agenda (Grup)',
                'description' => 'Rekap agenda kegiatan untuk grup.',
                'placeholders' => [
                    'judul',
                    'tanggal_label',
                    'agenda_list',
                    'generated_at',
                    'footer',
                ],
                'required_placeholders' => [
                    'judul',
                    'tanggal_label',
                    'agenda_list',
                    'generated_at',
                    'footer',
                ],
                'sections' => [
                    'header' => [
                        'label' => 'Header',
                        'template' => '{judul}',
                    ],
                    'body' => [
                        'label' => 'Agenda List',
                        'template' => implode("\n", [
                            'Agenda {tanggal_label}',
                            '',
                            '{agenda_list}',
                        ]),
                    ],
                    'footer' => [
                        'label' => 'Footer',
                        'template' => implode("\n", [
                            'Tanggal rekap: {generated_at}',
                            '',
                            '{footer}',
                        ]),
                    ],
                ],
                'list_item_template' => implode("\n", [
                    '*{no}. {judul}*',
                    '   ⏰ {waktu}',
                    '   📍 {tempat}',
                    '{personil_block}{keterangan_block}{surat_line}{lampiran_line}',
                ]),
                'list_item_placeholders' => [
                    'no',
                    'judul',
                    'waktu',
                    'tempat',
                    'personil_block',
                    'keterangan_block',
                    'surat_line',
                    'lampiran_line',
                ],
                'template' => implode("\n\n", [
                    '{judul}',
                    implode("\n", [
                        'Agenda {tanggal_label}',
                        '',
                        '{agenda_list}',
                    ]),
                    implode("\n", [
                        'Tanggal rekap: {generated_at}',
                        '',
                        '{footer}',
                    ]),
                ]),
            ],
            'group_belum_disposisi' => [
                'label' => 'Belum Disposisi (Grup)',
                'description' => 'Pesan daftar agenda yang belum disposisi.',
                'placeholders' => [
                    'agenda_list',
                    'leadership_block',
                    'footer',
                ],
                'required_placeholders' => [
                    'agenda_list',
                    'footer',
                ],
                'sections' => [
                    'header' => [
                        'label' => 'Header',
                        'template' => implode("\n", [
                            '*AGENDA MENUNGGU DISPOSISI PIMPINAN*',
                            '',
                            'Berikut daftar kegiatan yang belum mendapatkan disposisi pimpinan:',
                        ]),
                    ],
                    'body' => [
                        'label' => 'Agenda List',
                        'template' => '{agenda_list}',
                    ],
                    'footer' => [
                        'label' => 'Footer',
                        'template' => '{leadership_block}{footer}',
                    ],
                ],
                'list_item_template' => implode("\n", [
                    '*{no}. {judul}*',
                    ' *Tanggal*     : {tanggal}',
                    ' *Waktu*       : {waktu}',
                    ' *Tempat*      : {tempat}',
                    '',
                    '{surat_block}',
                ]),
                'list_item_placeholders' => [
                    'no',
                    'judul',
                    'tanggal',
                    'waktu',
                    'tempat',
                    'surat_block',
                ],
                'template' => implode("\n\n", [
                    implode("\n", [
                        '*AGENDA MENUNGGU DISPOSISI PIMPINAN*',
                        '',
                        'Berikut daftar kegiatan yang belum mendapatkan disposisi pimpinan:',
                    ]),
                    '{agenda_list}',
                    '{leadership_block}{footer}',
                ]),
            ],
            'follow_up_reminder' => [
                'label' => 'Pengingat Kegiatan Lainnya',
                'description' => 'Pesan pengingat tindak lanjut pekerjaan lainnya.',
                'placeholders' => [
                    'kegiatan_line',
                    'tanggal_line',
                    'jam_line',
                    'tempat_line',
                    'penerima_line',
                    'keterangan_block',
                    'kode_line',
                    'footer',
                ],
                'required_placeholders' => [
                    'kegiatan_line',
                    'tanggal_line',
                    'jam_line',
                    'kode_line',
                    'footer',
                ],
                'sections' => [
                    'header' => [
                        'label' => 'Header',
                        'template' => '*PENGINGAT TINDAK LANJUT*',
                    ],
                    'body' => [
                        'label' => 'Isi Pengingat',
                        'template' => implode("\n", [
                            '{kegiatan_line}',
                            '{tanggal_line}',
                            '{jam_line}',
                            '{tempat_line}{penerima_line}{keterangan_block}{kode_line}',
                        ]),
                    ],
                    'footer' => [
                        'label' => 'Footer',
                        'template' => '{footer}',
                    ],
                ],
                'template' => implode("\n\n", [
                    '*PENGINGAT TINDAK LANJUT*',
                    implode("\n", [
                        '{kegiatan_line}',
                        '{tanggal_line}',
                        '{jam_line}',
                        '{tempat_line}{penerima_line}{keterangan_block}{kode_line}',
                    ]),
                    '{footer}',
                ]),
            ],
            'vehicle_tax_reminder' => [
                'label' => 'Pengingat Pajak Kendaraan',
                'description' => 'Pesan pengingat pajak kendaraan.',
                'placeholders' => [
                    'type_label',
                    'pemegang',
                    'jenis',
                    'plat',
                    'pengurus_label',
                    'due_date',
                ],
                'required_placeholders' => [
                    'type_label',
                    'pemegang',
                    'jenis',
                    'plat',
                    'due_date',
                ],
                'sections' => [
                    'header' => [
                        'label' => 'Header',
                        'template' => '*Pengingat Pembayaran Pajak ({type_label})*',
                    ],
                    'body' => [
                        'label' => 'Isi Pengingat',
                        'template' => implode("\n", [
                            'Bapak/Ibu *{pemegang}*',
                            'Kendaraan {jenis} {plat} sudah masuk waktu perpanjangan pajak {type_label}, Mohon segera berkoordinasi dengan pengurus barang{pengurus_label}.',
                            '',
                            'batas waktu pembayaran pajak: *{due_date}*',
                        ]),
                    ],
                    'footer' => [
                        'label' => 'Footer',
                        'template' => 'Terima Kasih',
                    ],
                ],
                'template' => implode("\n\n", [
                    '*Pengingat Pembayaran Pajak ({type_label})*',
                    implode("\n", [
                        'Bapak/Ibu *{pemegang}*',
                        'Kendaraan {jenis} {plat} sudah masuk waktu perpanjangan pajak {type_label}, Mohon segera berkoordinasi dengan pengurus barang{pengurus_label}.',
                        '',
                        'batas waktu pembayaran pajak: *{due_date}*',
                    ]),
                    'Terima Kasih',
                ]),
            ],
        ];

        foreach ($definitions as $key => $definition) {
            $placeholders = $definition['placeholders'] ?? [];
            $definitions[$key]['placeholders'] = array_values(array_unique(array_merge(
                $placeholders,
                $commonPlaceholders,
            )));

            if (array_key_exists('list_item_placeholders', $definition)) {
                $itemPlaceholders = $definition['list_item_placeholders'] ?? [];
                $definitions[$key]['list_item_placeholders'] = array_values(array_unique(array_merge(
                    $itemPlaceholders,
                    $commonPlaceholders,
                )));
            }
        }

        return $definitions;
    }
}
