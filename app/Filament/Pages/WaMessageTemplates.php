<?php

namespace App\Filament\Pages;

use App\Models\WaMessageTemplate;
use App\Services\WaMessageTemplateService;
use App\Support\RoleAccess;
use App\Support\WaMessageTemplateDefaults;
use BackedEnum;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;

class WaMessageTemplates extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $title = 'Template Pesan WA';
    protected static ?string $navigationLabel = 'Template Pesan WA';
    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';
    protected static ?string $slug = 'wa-message-templates';
    protected static ?int $navigationSort = 27;

    protected string $view = 'filament.pages.wa-message-templates';

    public ?array $data = [];

    public function mount(): void
    {
        $state = [];

        foreach (WaMessageTemplateDefaults::definitions() as $key => $definition) {
            $record = WaMessageTemplate::query()->where('key', $key)->first();
            $content = $record?->content;
            $meta = is_array($record?->meta) ? $record->meta : [];

            $sections = $this->resolveSectionsState($definition, $content, $meta);

            foreach ($sections as $sectionKey => $value) {
                $state[$this->sectionFieldName($key, $sectionKey)] = $value;
            }

            if (! empty($definition['list_item_template'])) {
                $state[$this->listItemFieldName($key)] = $meta['item_template'] ?? $definition['list_item_template'];
                $state[$this->listSeparatorFieldName($key)] = $meta['item_separator'] ?? "\n\n";
            }

            $state[$this->tagFieldName($key)] = array_key_exists('include_personil_tag', $meta)
                ? (bool) $meta['include_personil_tag']
                : true;

            $state['preview_' . $key] = $this->buildPreview($key, $definition, $sections, $state);
        }

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        $sections = [];

        foreach (WaMessageTemplateDefaults::definitions() as $key => $definition) {
            $placeholders = $definition['placeholders'] ?? [];
            $placeholderList = $this->formatPlaceholderList($placeholders);
            $sectionFields = [];

            foreach ($definition['sections'] as $sectionKey => $sectionDefinition) {
                $sectionFields[] = MarkdownEditor::make($this->sectionFieldName($key, $sectionKey))
                    ->label($sectionDefinition['label'])
                    ->helperText('Gunakan Markdown (**bold**, __italic__, ~~coret~~). Placeholder tetap pakai {nama}.')
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set, callable $get) use ($key) {
                        $this->updatePreviewFromState($key, $set, $get);
                    })
                    ->columnSpanFull();
            }

            if (! empty($definition['list_item_template'])) {
                $itemPlaceholderList = $this->formatPlaceholderList($definition['list_item_placeholders'] ?? []);

                $sectionFields[] = Placeholder::make('item_placeholders_' . $key)
                    ->label('Placeholder item list')
                    ->content($itemPlaceholderList);

                $sectionFields[] = MarkdownEditor::make($this->listItemFieldName($key))
                    ->label('Template item list')
                    ->helperText('Atur format per baris agenda (Markdown + placeholder).')
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set, callable $get) use ($key) {
                        $this->updatePreviewFromState($key, $set, $get);
                    })
                    ->columnSpanFull();

                $sectionFields[] = Select::make($this->listSeparatorFieldName($key))
                    ->label('Pemisah antar item')
                    ->options($this->listSeparatorOptions())
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set, callable $get) use ($key) {
                        $this->updatePreviewFromState($key, $set, $get);
                    })
                    ->columnSpanFull();
            }

            $sections[] = Section::make($definition['label'])
                ->description($definition['description'])
                ->schema([
                    Placeholder::make('placeholders_' . $key)
                        ->label('Placeholder tersedia')
                        ->content($placeholderList),
                    Toggle::make($this->tagFieldName($key))
                        ->label('Tag nomor personil')
                        ->helperText('Aktifkan untuk menyertakan mention nomor WA (@62...) di template ini.')
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) use ($key) {
                            $this->updatePreviewFromState($key, $set, $get);
                        }),
                    ...$sectionFields,
                    Textarea::make('preview_' . $key)
                        ->label('Preview (data dummy, format WA)')
                        ->rows(10)
                        ->disabled()
                        ->dehydrated(false)
                        ->columnSpanFull(),
                ])
                ->columns(1);
        }

        return $schema
            ->components($sections)
            ->statePath('data');
    }

    protected function formatPlaceholderList(array $placeholders): string
    {
        if (empty($placeholders)) {
            return '-';
        }

        return '{' . implode('}, {', $placeholders) . '}';
    }

    protected function sectionFieldName(string $key, string $sectionKey): string
    {
        return $key . '__section__' . $sectionKey;
    }

    protected function listItemFieldName(string $key): string
    {
        return $key . '__list_item_template';
    }

    protected function listSeparatorFieldName(string $key): string
    {
        return $key . '__list_item_separator';
    }

    protected function tagFieldName(string $key): string
    {
        return $key . '__include_personil_tag';
    }

    /**
     * @return array<string, string>
     */
    protected function listSeparatorOptions(): array
    {
        return [
            "\n\n" => 'Baris kosong',
            "\n" => 'Baris baru',
            "\n────────────────────────\n" => 'Garis pemisah',
        ];
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $meta
     * @return array<string, string>
     */
    protected function resolveSectionsState(array $definition, ?string $content, array $meta): array
    {
        if (! empty($meta['sections']) && is_array($meta['sections'])) {
            return array_map('strval', $meta['sections']);
        }

        $sections = [];
        foreach (($definition['sections'] ?? []) as $sectionKey => $sectionDefinition) {
            $sections[$sectionKey] = (string) ($sectionDefinition['template'] ?? '');
        }

        if ($content !== null && trim($content) !== '') {
            $fallback = array_fill_keys(array_keys($sections), '');
            $targetKey = array_key_exists('body', $fallback) ? 'body' : array_key_first($fallback);
            if ($targetKey) {
                $fallback[$targetKey] = $content;
                return $fallback;
            }

            return ['body' => $content];
        }

        return $sections;
    }

    /**
     * @param array<string, string> $sections
     */
    protected function compileTemplateFromSections(array $sections): string
    {
        $parts = [];

        foreach ($sections as $section) {
            $value = trim((string) $section);
            if ($value !== '') {
                $parts[] = $value;
            }
        }

        return implode("\n\n", $parts);
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, string> $sections
     * @param array<string, mixed> $state
     */
    protected function buildPreview(string $key, array $definition, array $sections, array $state): string
    {
        $template = $this->compileTemplateFromSections($sections);
        if ($template === '') {
            $template = (string) ($definition['template'] ?? '');
        }

        $includeTag = (bool) ($state[$this->tagFieldName($key)] ?? true);
        $data = $this->dummyDataFor($key, $includeTag);

        if (! empty($definition['list_item_template'])) {
            $itemTemplate = (string) ($state[$this->listItemFieldName($key)] ?? $definition['list_item_template']);
            $separator = (string) ($state[$this->listSeparatorFieldName($key)] ?? "\n\n");
            $data['agenda_list'] = $this->buildDummyAgendaList($key, $itemTemplate, $separator, $includeTag);
        }

        return $this->renderPreview($template, $data);
    }

    protected function updatePreviewFromState(string $key, callable $set, callable $get): void
    {
        $definitions = WaMessageTemplateDefaults::definitions();
        $definition = $definitions[$key] ?? null;

        if (! $definition) {
            return;
        }

        $sections = [];
        foreach ($definition['sections'] as $sectionKey => $sectionDefinition) {
            $sections[$sectionKey] = (string) ($get($this->sectionFieldName($key, $sectionKey)) ?? '');
        }

        $template = $this->compileTemplateFromSections($sections);
        if ($template === '') {
            $template = (string) ($definition['template'] ?? '');
        }

        $includeTag = (bool) ($get($this->tagFieldName($key)) ?? true);
        $data = $this->dummyDataFor($key, $includeTag);

        if (! empty($definition['list_item_template'])) {
            $itemTemplate = (string) ($get($this->listItemFieldName($key)) ?? $definition['list_item_template']);
            $separator = (string) ($get($this->listSeparatorFieldName($key)) ?? "\n\n");
            $data['agenda_list'] = $this->buildDummyAgendaList($key, $itemTemplate, $separator, $includeTag);
        }

        $set('preview_' . $key, $this->renderPreview($template, $data));
    }

    /**
     * @return array<string, string>
     */
    protected function dummyDataFor(string $key, bool $includeTag = true): array
    {
        $data = match ($key) {
            'agenda_group' => [
                'tanggal_header' => 'Senin, 12 Januari 2026',
                'judul' => 'Rapat Koordinasi Evaluasi Program',
                'waktu' => '09:00 WIB',
                'tempat' => 'Ruang Rapat Utama',
                'peserta_line' => $includeTag
                    ? "   👥 Camat, Sekcam\n      @6281234567890 @6289876543210\n"
                    : "   👥 Camat, Sekcam, Budi\n",
                'peserta_raw' => $includeTag ? 'Camat, Sekcam' : 'Camat, Sekcam, Budi',
                'mentions_raw' => $includeTag ? '@6281234567890 @6289876543210' : '',
                'mentions_line' => $includeTag ? "      @6281234567890 @6289876543210\n" : '',
                'personil_list_raw' => $includeTag
                    ? implode("\n", [
                        '1. Budi',
                        '   @6281234567890',
                        '2. Sari',
                        '   @6289876543210',
                    ])
                    : implode("\n", [
                        '1. Budi',
                        '2. Sari',
                    ]),
                'keterangan_line' => "   📝 Dimohon hadir tepat waktu.\n",
                'keterangan_raw' => 'Dimohon hadir tepat waktu.',
                'surat_line' => "   📎 Surat: https://example.com/surat\n",
                'surat_url' => 'https://example.com/surat',
                'lampiran_line' => "   📎 Lampiran: https://example.com/lampiran\n",
                'lampiran_url' => 'https://example.com/lampiran',
                'footer' => "Harap selalu laporkan hasil kegiatan kepada atasan.\nPesan ini dikirim otomatis dari sistem agenda kantor.",
            ],
            'agenda_personil' => [
                'nama_kegiatan' => 'Sosialisasi Program Desa',
                'nomor_surat' => '005/XYZ/2026',
                'tanggal' => 'Senin, 12 Januari 2026',
                'waktu' => '09:00 WIB',
                'tempat' => 'Balai Desa',
                'keterangan_block' => "*Keterangan*\nMohon membawa bahan paparan.\n\n",
                'keterangan_raw' => 'Mohon membawa bahan paparan.',
                'surat_block' => "📎 *Lihat Surat (PDF)*\nhttps://example.com/surat\n\n",
                'surat_url' => 'https://example.com/surat',
                'lampiran_block' => "📎 *Lampiran*\nhttps://example.com/lampiran\n\n",
                'lampiran_url' => 'https://example.com/lampiran',
                'footer' => "_Harap selalu laporkan hasil kegiatan kepada atasan._\n_Pesan ini dikirim otomatis. Mohon tidak membalas ke nomor ini._",
            ],
            'tindak_lanjut_reminder' => [
                'nomor_surat' => '123/ABC/2026',
                'kode_tl' => 'TL-45',
                'perihal' => 'Klarifikasi Dokumen',
                'tanggal' => 'Senin, 12 Januari 2026',
                'batas_tl' => 'Selasa, 13 Januari 2026 10:00 WIB',
                'label_lines' => implode("\n", [
                    'Kode TL       : TL-45',
                    'Perihal       : Klarifikasi Dokumen',
                    'Tanggal       : Senin, 12 Januari 2026',
                    'Batas TL      : Selasa, 13 Januari 2026 10:00 WIB',
                ]),
                'surat_block' => "📎 Surat (PDF):\nhttps://example.com/surat\n\n",
                'surat_url' => 'https://example.com/surat',
                'lampiran_block' => "📎 Lampiran Surat:\nhttps://example.com/lampiran\n\n",
                'lampiran_url' => 'https://example.com/lampiran',
                'disposisi_block' => $includeTag
                    ? "Mohon arahan percepatan tindak lanjut:\n@6281234567890\nkepada: @6289876543210\n\n"
                    : "Mohon arahan percepatan tindak lanjut:\nCamat, Sekcam\nkepada: Budi\n\n",
                'disposisi_tags' => $includeTag ? '@6281234567890' : 'Camat, Sekcam',
                'personil_tags' => $includeTag ? '@6289876543210' : 'Budi',
                'balasan_line' => '_Balas pesan ini dengan *TL-45 selesai* jika sudah menyelesaikan TL_' . "\n",
                'footer' => "_Harap selalu laporkan hasil kegiatan kepada atasan._\n_Pesan ini dikirim otomatis saat batas waktu tindak lanjut tercapai._",
            ],
            'group_rekap' => [
                'judul' => 'REKAP AGENDA KEGIATAN KANTOR',
                'tanggal_label' => 'Senin, 12 Januari 2026',
                'agenda_list' => '',
                'generated_at' => '12 Januari 2026 08:00 WIB',
                'footer' => "Harap selalu laporkan hasil kegiatan kepada atasan.\nPesan ini dikirim otomatis dari sistem agenda kantor.",
            ],
            'group_belum_disposisi' => [
                'agenda_list' => '',
                'leadership_block' => $includeTag
                    ? "*Mohon petunjuk/arahan disposisi:*\n@6281234567890\n\n"
                    : "*Mohon petunjuk/arahan disposisi:*\nCamat, Sekcam\n\n",
                'leadership_tags' => $includeTag ? '@6281234567890' : 'Camat, Sekcam',
                'footer' => "_Harap selalu laporkan hasil kegiatan kepada atasan._\n_Pesan ini dikirim otomatis dari sistem agenda kantor._",
            ],
            'follow_up_reminder' => [
                'kegiatan_line' => 'Kegiatan  : Verifikasi Dokumen',
                'kegiatan' => 'Verifikasi Dokumen',
                'tanggal_line' => 'Tanggal   : Senin, 12 Januari 2026',
                'tanggal' => 'Senin, 12 Januari 2026',
                'jam_line' => 'Jam       : 09:30 WIB',
                'jam' => '09:30 WIB',
                'tempat_line' => 'Tempat    : Ruang Arsip' . "\n",
                'tempat' => 'Ruang Arsip',
                'penerima_line' => $includeTag
                    ? 'Untuk     : @6281234567890 (Budi)' . "\n"
                    : 'Untuk     : Budi' . "\n",
                'penerima' => $includeTag ? '@6281234567890 (Budi)' : 'Budi',
                'penerima_mention' => $includeTag ? '@6281234567890' : '',
                'keterangan_block' => "\n*Keterangan:*\nMohon siapkan dokumen pendukung.\n\n",
                'keterangan' => 'Mohon siapkan dokumen pendukung.',
                'kode_line' => 'Kode      : PR-12',
                'kode' => 'PR-12',
                'footer' => implode("\n", [
                    'Mohon tindak lanjuti kegiatan di atas.',
                    'Balas pesan ini dengan kata kunci *terima kasih* untuk menghentikan pengingat.',
                    'Jika ada banyak, bisa balas: *terima kasih 12* (kode) atau *terima kasih semua*.',
                ]),
            ],
            'vehicle_tax_reminder' => [
                'type_label' => '1 tahunan',
                'pemegang' => 'Bapak Ahmad',
                'jenis' => 'Mobil',
                'plat' => 'AA 1234 BC',
                'pengurus_label' => ' (Pengurus Barang)',
                'due_date' => '12 Januari 2026',
            ],
            default => [],
        };

        return $data;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function dummyListItemsFor(string $key, bool $includeTag = true): array
    {
        return match ($key) {
            'group_rekap' => [
                [
                    'no' => '1',
                    'judul' => 'Rapat Evaluasi',
                    'waktu' => '09:00 WIB',
                    'tempat' => 'Ruang Rapat Utama',
                    'personil_block' => implode("\n", [
                        '   👥 Penerima Disposisi:',
                        $includeTag ? '      1. Budi' : '      1. Budi',
                        $includeTag ? '         @6281234567890' : '',
                        '',
                    ]),
                    'personil_list_raw' => $includeTag
                        ? implode("\n", [
                            '1. Budi',
                            '   @6281234567890',
                        ])
                        : '1. Budi',
                    'personil_names_raw' => 'Budi',
                    'personil_mentions_raw' => $includeTag ? '@6281234567890' : '',
                    'keterangan_block' => implode("\n", [
                        '   📝 Keterangan:',
                        '      Dimohon hadir tepat waktu.',
                        '',
                    ]),
                    'keterangan_raw' => 'Dimohon hadir tepat waktu.',
                    'surat_line' => '   📎 Link Surat: https://example.com/surat' . "\n",
                    'surat_url' => 'https://example.com/surat',
                    'lampiran_line' => '   📎 Lampiran: https://example.com/lampiran' . "\n",
                    'lampiran_url' => 'https://example.com/lampiran',
                ],
                [
                    'no' => '2',
                    'judul' => 'Kunjungan Lapangan',
                    'waktu' => '13:30 WIB',
                    'tempat' => 'Desa Sumber',
                    'personil_block' => implode("\n", [
                        '   👥 Penerima Disposisi:',
                        $includeTag ? '      1. Sari' : '      1. Sari',
                        $includeTag ? '         @6289876543210' : '',
                        '',
                    ]),
                    'personil_list_raw' => $includeTag
                        ? implode("\n", [
                            '1. Sari',
                            '   @6289876543210',
                        ])
                        : '1. Sari',
                    'personil_names_raw' => 'Sari',
                    'personil_mentions_raw' => $includeTag ? '@6289876543210' : '',
                    'keterangan_block' => '',
                    'keterangan_raw' => '',
                    'surat_line' => '',
                    'surat_url' => '',
                    'lampiran_line' => '',
                    'lampiran_url' => '',
                ],
            ],
            'group_belum_disposisi' => [
                [
                    'perlu_tindak_lanjut' => true,
                    'nomor_surat' => '123/ABC/2026',
                    'no' => '1',
                    'judul' => 'Permohonan Data',
                    'perihal' => 'Klarifikasi Dokumen',
                    'tanggal' => 'Senin, 12 Januari 2026',
                    'waktu' => '09:00 WIB',
                    'tempat' => 'Ruang Camat',
                    'keterangan_block' => implode("\n", [
                        '   📝 Keterangan:',
                        '      Mohon ditindaklanjuti.',
                        '',
                    ]),
                    'keterangan_raw' => 'Mohon verifikasi dokumen pendukung dan koordinasi lintas bidang.',
                    'batas_tl' => 'Selasa, 13 Januari 2026 10:00 WIB',
                    'surat_block' => implode("\n", [
                        '📎 *Lihat Surat (PDF)*',
                        'https://example.com/surat',
                    ]) . "\n",
                    'surat_url' => 'https://example.com/surat',
                    'lampiran_url' => 'https://example.com/lampiran',
                    'leadership_tags' => $includeTag ? '@6281234567890' : 'Camat, Sekcam',
                    'personil_list_raw' => $includeTag
                        ? implode("\n", [
                            '1. Budi',
                            '   @6281234567890',
                        ])
                        : '1. Budi',
                    'personil_names_raw' => 'Budi',
                    'personil_mentions_raw' => $includeTag ? '@6281234567890' : '',
                ],
                [
                    'perlu_tindak_lanjut' => false,
                    'no' => '2',
                    'judul' => 'Permintaan Dukungan',
                    'tanggal' => 'Selasa, 13 Januari 2026',
                    'waktu' => '10:30 WIB',
                    'tempat' => 'Aula Kecamatan',
                    'keterangan_block' => '',
                    'keterangan_raw' => '',
                    'surat_block' => '',
                    'surat_url' => '',
                    'personil_list_raw' => '',
                    'personil_names_raw' => '',
                    'personil_mentions_raw' => '',
                ],
            ],
            default => [],
        };
    }

    protected function buildDummyAgendaList(string $key, string $itemTemplate, string $separator, bool $includeTag): string
    {
        $items = $this->dummyListItemsFor($key, $includeTag);

        if (empty($items)) {
            return '';
        }

        $template = trim($itemTemplate);
        if ($template === '') {
            return '';
        }

        /** @var WaMessageTemplateService $templateService */
        $templateService = app(WaMessageTemplateService::class);

        $rendered = [];
        foreach ($items as $item) {
            if ($key === 'group_belum_disposisi' && ! empty($item['perlu_tindak_lanjut'])) {
                $rendered[] = $this->buildDummyBelumDisposisiTindakLanjutBlock($item);
                continue;
            }
            $rendered[] = $templateService->renderString($template, $item);
        }

        if ($key === 'group_belum_disposisi') {
            $rendered[] = '_Mohon tindak lanjut disposisi sesuai kewenangan._';
        }

        return implode($separator, $rendered);
    }

    /**
     * @param array<string, mixed> $item
     */
    protected function buildDummyBelumDisposisiTindakLanjutBlock(array $item): string
    {
        $nomorSurat = trim((string) ($item['nomor_surat'] ?? ''));
        if ($nomorSurat === '') {
            $nomorSurat = '-';
        }

        $perihal = trim((string) ($item['perihal'] ?? $item['judul'] ?? ''));
        if ($perihal === '') {
            $perihal = '-';
        }

        $tanggal = trim((string) ($item['tanggal'] ?? ''));
        if ($tanggal === '') {
            $tanggal = '-';
        }

        $keterangan = trim((string) ($item['keterangan_raw'] ?? ''));
        if ($keterangan === '') {
            $keterangan = '-';
        }

        $batasTl = trim((string) ($item['batas_tl'] ?? ''));
        if ($batasTl === '') {
            $batasTl = '-';
        }

        $suratUrl = trim((string) ($item['surat_url'] ?? ''));
        $lampiranUrl = trim((string) ($item['lampiran_url'] ?? ''));
        $leadershipTags = trim((string) ($item['leadership_tags'] ?? ''));

        $lines = [
            '*MOHON DISPOSISI — SURAT PERLU TL*',
            '',
            $this->formatLabelLine('Nomor Surat', $nomorSurat),
            $this->formatLabelLine('Perihal', $perihal),
            $this->formatLabelLine('Tanggal', $tanggal),
            $this->formatLabelLine('Keterangan', $keterangan),
            $this->formatLabelLine('Batas TL', $batasTl),
            '',
        ];

        if ($suratUrl !== '') {
            $lines[] = '📎 Surat (PDF):';
            $lines[] = $suratUrl;
            $lines[] = '';
        }

        if ($lampiranUrl !== '') {
            $lines[] = '📎 Lampiran:';
            $lines[] = $lampiranUrl;
            $lines[] = '';
        }

        $lines[] = 'Mohon petunjuk penugasan/arahannya.:';
        if ($leadershipTags !== '') {
            $lines[] = $leadershipTags;
        }

        return trim(implode("\n", $lines));
    }

    protected function formatLabelLine(string $label, string $value): string
    {
        return sprintf('%-14s: %s', $label, $value);
    }

    /**
     * @param array<string, string> $data
     */
    protected function renderPreview(string $template, array $data): string
    {
        /** @var WaMessageTemplateService $templateService */
        $templateService = app(WaMessageTemplateService::class);

        return $templateService->renderString($template, $data);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $definitions = WaMessageTemplateDefaults::definitions();
        $payloads = [];
        $errors = [];

        foreach ($definitions as $key => $definition) {
            $sections = [];

            foreach ($definition['sections'] as $sectionKey => $sectionDefinition) {
                $sections[$sectionKey] = trim((string) ($state[$this->sectionFieldName($key, $sectionKey)] ?? ''));
            }

            $content = $this->compileTemplateFromSections($sections);

            if ($content === '') {
                $payloads[$key] = null;
                continue;
            }

            $missing = [];
            foreach (($definition['required_placeholders'] ?? []) as $placeholder) {
                if (! str_contains($content, '{' . $placeholder . '}')) {
                    $missing[] = $placeholder;
                }
            }

            if (! empty($missing)) {
                $errors[] = $definition['label'] . ': ' . $this->formatPlaceholderList($missing);
            }

            $meta = ['sections' => $sections];

            if (! empty($definition['list_item_template'])) {
                $itemTemplate = trim((string) ($state[$this->listItemFieldName($key)] ?? ''));
                if ($itemTemplate === '') {
                    $itemTemplate = (string) $definition['list_item_template'];
                }

                $meta['item_template'] = $itemTemplate;
                $meta['item_separator'] = (string) ($state[$this->listSeparatorFieldName($key)] ?? "\n\n");
            }

            $meta['include_personil_tag'] = (bool) ($state[$this->tagFieldName($key)] ?? true);

            $payloads[$key] = [
                'content' => $content,
                'meta' => $meta,
            ];
        }

        if (! empty($errors)) {
            Notification::make()
                ->title('Template belum valid')
                ->body(implode("\n", $errors))
                ->danger()
                ->send();
            return;
        }

        foreach ($payloads as $key => $payload) {
            if (! $payload) {
                WaMessageTemplate::query()->where('key', $key)->delete();
                continue;
            }

            WaMessageTemplate::query()->updateOrCreate(
                ['key' => $key],
                $payload
            );
        }

        Notification::make()
            ->title('Template pesan WA disimpan')
            ->success()
            ->send();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->isAdmin() === true;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return RoleAccess::canSeeNav(auth()->user(), 'filament.admin.pages.wa-message-templates');
    }
}
