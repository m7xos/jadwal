<?php

namespace App\Filament\Resources\Kegiatans\Tables;

use App\Models\Kegiatan;
use App\Services\SppdGenerator;
use App\Services\SuratTugasGenerator;
use App\Services\WaGatewayService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
//tambahan
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;


class KegiatansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nomor')
                    ->label('No.')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('sifat_surat')
                    ->label('Sifat Surat')
                    ->formatStateUsing(function (?string $state): string {
                        return match ($state) {
                            'undangan' => 'Undangan',
                            'edaran' => 'Surat Edaran',
                            'pemberitahuan' => 'Pemberitahuan',
                            default => 'Lainnya',
                        };
                    })
                    ->badge()
                    ->colors([
                        'primary' => 'undangan',
                        'warning' => 'edaran',
                        'success' => 'pemberitahuan',
                        'gray' => 'lainnya',
                    ]),

                TextColumn::make('nama_kegiatan')
                    ->label('Nama Kegiatan')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('tanggal')
                    ->label('Hari / Tanggal')
                    ->date('l, d-m-Y') // ini masih Inggris, bisa diganti accessor kalau mau full Indonesia
                    ->sortable(),

                TextColumn::make('waktu')
                    ->label('Waktu')
                    ->sortable()
                    ->visible(fn (?Kegiatan $record) => filled($record?->waktu)),

                TextColumn::make('tempat')
                    ->label('Tempat')
                    ->searchable()
                    ->wrap()
                    ->visible(fn (?Kegiatan $record) => filled($record?->tempat)),

               // TextColumn::make('personils_count')
                //    ->label('Jml Personil')
                 //   ->counts('personils'),

                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->limit(40)
                    ->tooltip(fn ($state) => $state)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('batas_tindak_lanjut')
                    ->label('Batas TL')
                    ->dateTime('d-m-Y H:i')
                    ->toggleable()
                    ->visible(fn (?Kegiatan $record) => (bool) $record?->perlu_tindak_lanjut),

                TextColumn::make('tindak_lanjut_selesai_at')
                    ->label('Status TL')
                    ->state(function (Kegiatan $record): ?string {
                        if (! $record->perlu_tindak_lanjut) {
                            return null;
                        }

                        return $record->tindak_lanjut_selesai_at ? 'Selesai TL' : 'Belum TL';
                    })
                    ->formatStateUsing(fn (?string $state) => $state ?? '-')
                    ->badge()
                    ->colors([
                        'success' => fn (?string $state) => $state === 'Selesai TL',
                        'danger' => fn (?string $state) => $state === 'Belum TL',
                    ])
                    ->tooltip(fn (?string $state) => $state === 'Selesai TL'
                        ? 'Sudah selesai tindak lanjut'
                        : ($state === 'Belum TL' ? 'Belum selesai tindak lanjut' : ''))
                    // allow rendering header when $record null; hide for undangan rows
                    ->visible(fn ($record) => $record === null || (bool) $record?->perlu_tindak_lanjut),
            ])
            ->defaultSort('tanggal', 'asc')

            // ================== FILTER HARIAN ==================
            ->filters([
                SelectFilter::make('tahun_surat')
                    ->label('Tahun Surat')
                    ->options(function (): array {
                        $years = Kegiatan::query()
                            ->whereNotNull('tanggal')
                            ->selectRaw('YEAR(tanggal) as year')
                            ->distinct()
                            ->orderByDesc('year')
                            ->pluck('year')
                            ->map(fn ($year) => (string) $year)
                            ->all();

                        $currentYear = (string) now()->year;

                        if (! in_array($currentYear, $years, true)) {
                            array_unshift($years, $currentYear);
                        }

                        return array_combine($years, $years) ?: [];
                    })
                    ->default((string) now()->year)
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (! $value) {
                            return $query;
                        }

                        return $query->whereYear('tanggal', (int) $value);
                    }),
                // Tombol cepat: "Hari Ini"
                Filter::make('hari_ini')
                    ->label('Hari Ini')
                    ->query(fn (Builder $query): Builder =>
                        $query->whereDate('tanggal', today())
                    )
                    ->toggle(),

                // Filter pilih tanggal manual
                Filter::make('tanggal')
                    ->label('Filter Tanggal')
                    ->form([
                        Forms\Components\DatePicker::make('tanggal')
                            ->label('Tanggal Kegiatan'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['tanggal'] ?? null,
                                fn (Builder $query, $date): Builder =>
                                    $query->whereDate('tanggal', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): ?array {
                        if (empty($data['tanggal'])) {
                            return null;
                        }

                        return [
                            'tanggal' => 'Tanggal: ' . Carbon::parse($data['tanggal'])
                                ->locale('id')
                                ->isoFormat('dddd, D MMMM Y'),
                        ];
                    }),

                Filter::make('belum_disposisi')
                    ->label('Belum Disposisi')
                    ->query(fn (Builder $query): Builder => $query->where('sudah_disposisi', false))
                    ->toggle(),

                Filter::make('perlu_tindak_lanjut')
                    ->label('Perlu TL')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('perlu_tindak_lanjut', true)
                        ->whereNull('tindak_lanjut_selesai_at')),
            ])

            // ================== AKSI PER RECORD ==================
           
            ->recordActions([
                EditAction::make(),

                ActionGroup::make([
                    Action::make('buat_surat_tugas')
                        ->label('Buat Surat Tugas')
                        ->icon('heroicon-o-document-text')
                        ->color('success')
                        ->url(fn (Kegiatan $record) => route('kegiatan.surat_tugas', $record))
                        ->openUrlInNewTab()
                        ->tooltip('Generate surat tugas dari template dan unduh.'),

                    Action::make('buat_sppd')
                        ->label('Buat SPPD')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('primary')
                        ->url(fn (Kegiatan $record) => route('kegiatan.sppd', $record))
                        ->openUrlInNewTab()
                        ->tooltip('Generate SPPD per personil; jika lebih dari 1 akan otomatis ZIP.'),
                ])
                    ->label('Surat Tugas')
                    ->icon('heroicon-o-document-text'),

                DeleteAction::make(),
            ])

            // ================== BULK ACTION (SESUAI FILTER) ==================
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('kirim_wa_group')
                        ->label('Kirim Rekap Group')
                        ->icon('heroicon-o-paper-airplane')
                        ->requiresConfirmation()
                        ->modalHeading('Kirim agenda terpilih ke grup WhatsApp masing-masing?')
                        ->action(function (Collection $records) {
                            if ($records->isEmpty()) {
                                Notification::make()
                                    ->title('Tidak ada data')
                                    ->body('Pilih minimal satu agenda terlebih dahulu.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            /** @var WaGatewayService $waGateway */
                            $waGateway = app(WaGatewayService::class);

                            $records->load('groups', 'personils');

                            $groupBuckets = [];
                            $missingGroups = [];

                            foreach ($records as $record) {
                                $recordGroups = $record->groups ?? collect();

                                if ($recordGroups->isEmpty()) {
                                    $missingGroups[] = $record->nama_kegiatan ?? 'Agenda';
                                    continue;
                                }

                                foreach ($recordGroups as $group) {
                                    if (! $group || ! $group->id) {
                                        continue;
                                    }

                                    if (! array_key_exists($group->id, $groupBuckets)) {
                                        $groupBuckets[$group->id] = [
                                            'group' => $group,
                                            'records' => collect(),
                                        ];
                                    }

                                    $groupBuckets[$group->id]['records']->push($record);
                                }
                            }

                            if (empty($groupBuckets)) {
                                Notification::make()
                                    ->title('Tidak ada grup tujuan')
                                    ->body('Agenda terpilih belum memiliki grup tujuan.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $results = [];
                            $success = false;

                            foreach ($groupBuckets as $groupId => $bucket) {
                                /** @var \Illuminate\Support\Collection $bucketRecords */
                                $bucketRecords = $bucket['records']->unique('id')->values();

                                if ($bucketRecords->count() === 1) {
                                    /** @var Kegiatan $singleRecord */
                                    $singleRecord = $bucketRecords->first();
                                    $result = $waGateway->sendAgendaToGroups($singleRecord, [$groupId]);
                                } else {
                                    $result = $waGateway->sendGroupRekapToGroups($bucketRecords, [$groupId]);
                                }

                                $resultEntry = $result['results'][$groupId] ?? null;
                                if ($resultEntry) {
                                    $results[$groupId] = $resultEntry;
                                    if ($resultEntry['success'] ?? false) {
                                        $success = true;
                                    }
                                }
                            }

                            $sentGroups = collect($groupBuckets)
                                ->filter(fn ($bucket, $groupId) => (bool) ($results[$groupId]['success'] ?? false))
                                ->map(fn ($bucket) => $bucket['group']?->nama)
                                ->filter()
                                ->implode(', ');

                            $failedGroups = collect($groupBuckets)
                                ->filter(fn ($bucket, $groupId) => ! ($results[$groupId]['success'] ?? false))
                                ->map(fn ($bucket) => $bucket['group']?->nama)
                                ->filter()
                                ->implode(', ');

                            $messages = [];
                            if ($sentGroups !== '') {
                                $messages[] = 'Terkirim ke: ' . $sentGroups;
                            }
                            if ($failedGroups !== '') {
                                $messages[] = 'Gagal: ' . $failedGroups;
                            }
                            if (! empty($missingGroups)) {
                                $messages[] = 'Tanpa grup: ' . implode(', ', array_unique($missingGroups));
                            }

                            $body = implode("\n", $messages);

                            if ($success) {
                                Notification::make()
                                    ->title('Selesai')
                                    ->body($body ?: 'Rekap agenda dikirim ke grup terpilih.')
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Gagal')
                                    ->body($body ?: 'Tidak ada pesan yang dikirim.')
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion()
                        ->tooltip('Agenda terpilih dikirim ke grup WA masing-masing sesuai relasinya.'),
                    BulkAction::make('kirim_wa_belum_disposisi')
                        ->label('Mohon Disposisi (Filter)')
                        ->icon('heroicon-o-paper-airplane')
                        ->requiresConfirmation()
                        ->modalHeading('Kirim ke grup WhatsApp daftar agenda yang belum disposisi (berdasarkan filter saat ini)?')
                        ->action(function (Collection $records) {
                            $records = $records->where('sudah_disposisi', false);

                            if ($records->isEmpty()) {
                                Notification::make()
                                    ->title('Tidak ada data')
                                    ->body('Tidak ada agenda berstatus belum disposisi yang dicentang.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $records->load('personils'); // tidak wajib, tapi kalau mau pakai nanti aman

                            /** @var WaGatewayService $waGateway */
                            $waGateway = app(WaGatewayService::class);

                            $success = $waGateway->sendGroupBelumDisposisi($records);

                            if ($success) {
                                Notification::make()
                                    ->title('Berhasil')
                                    ->body('Daftar agenda yang belum disposisi berhasil dikirim ke grup WhatsApp.')
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Gagal')
                                    ->body('Gagal mengirim pesan ke WA Gateway. Cek konfigurasi/token/ID grup.')
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion()
                        ->tooltip('Mengirim ke grup WA daftar agenda yang belum disposisi, hanya untuk agenda yang dicentang.'),

                    DeleteBulkAction::make(),
                ]),

                BulkAction::make('cetak_disposisi')
                    ->label('Cetak Disposisi')
                    ->icon('heroicon-o-printer')
                    ->alpineClickHandler(function (): string {
                        $baseUrl = route('kegiatan.disposisi.bulk', ['ids' => '__IDS__']);
                        $escapedUrl = str_replace("'", "\\'", $baseUrl);

                        return "const records = [...selectedRecords];"
                            . "if (records.length < 1) {"
                            . "window.alert('Pilih minimal satu agenda untuk mencetak disposisi.');"
                            . "return;"
                            . "}"
                            . "const url = '{$escapedUrl}';"
                            . "window.open(url.replace('__IDS__', records.join(',')), '_blank');";
                    })
                    ->tooltip('Pilih minimal satu agenda untuk mencetak disposisi.'),

                BulkActionGroup::make([
                    BulkAction::make('buat_surat_tugas')
                        ->label('Buat Surat Tugas')
                        ->icon('heroicon-o-document-text')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            if ($records->count() !== 1) {
                                Notification::make()
                                    ->title('Pilih satu agenda')
                                    ->body('Pilih tepat satu agenda untuk membuat surat tugas.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $record = $records->first();

                            /** @var SuratTugasGenerator $generator */
                            $generator = app(SuratTugasGenerator::class);

                            $generator->generate($record);

                            return redirect()->route('kegiatan.surat_tugas', $record);
                        })
                        ->deselectRecordsAfterCompletion()
                        ->tooltip('Buat surat tugas untuk agenda terpilih. Pilih satu agenda saja.'),

                    BulkAction::make('buat_sppd')
                        ->label('Buat SPPD')
                        ->icon('heroicon-o-document-duplicate')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            if ($records->count() !== 1) {
                                Notification::make()
                                    ->title('Pilih satu agenda')
                                    ->body('Pilih tepat satu agenda untuk membuat SPPD.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $record = $records->first();

                            /** @var SppdGenerator $generator */
                            $generator = app(SppdGenerator::class);

                            $generator->generate($record);

                            return redirect()->route('kegiatan.sppd', $record);
                        })
                        ->deselectRecordsAfterCompletion()
                        ->tooltip('Buat SPPD untuk agenda terpilih (jika personil lebih dari 1, hasil ZIP).'),
                ])
                    ->label('Surat Tugas')
                    ->icon('heroicon-o-document-text'),
            ]);
    }
}
