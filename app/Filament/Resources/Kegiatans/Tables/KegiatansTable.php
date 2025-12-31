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
                    ->query(fn (Builder $query): Builder => $query->where('perlu_tindak_lanjut', true)),
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

                // Kirim ke WA semua personil 1 kegiatan
                ActionGroup::make([
                    Action::make('kirim_wa_personil')
                        ->label('Kirim WA Personil')
                        ->icon('heroicon-o-chat-bubble-left-right')
                        ->requiresConfirmation()
                        ->modalHeading('Kirim agenda ini ke WA personil yang hadir?')
                        ->action(function (Kegiatan $record) {
                            /** @var WaGatewayService $waGateway */
                            $waGateway = app(WaGatewayService::class);

                            $record->loadMissing('personils');

                            $success = $waGateway->sendToPersonils($record);

                            if ($success) {
                                Notification::make()
                                    ->title('Berhasil')
                                    ->body('Agenda berhasil dikirim ke WA seluruh personil yang hadir.')
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Gagal')
                                    ->body('Gagal mengirim ke WA personil. Pastikan nomor WA terisi dan konfigurasi WA Gateway benar.')
                                    ->danger()
                                    ->send();
                            }
                        }),
                ])
                    ->label('Kirim WA')
                    ->icon('heroicon-o-chat-bubble-left-right'),

                DeleteAction::make(),
            ])

            // ================== BULK ACTION (SESUAI FILTER) ==================
            ->toolbarActions([
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

                BulkActionGroup::make([
                    BulkAction::make('kirim_wa_group')
                        ->label('Kirim Group')
                        ->icon('heroicon-o-paper-airplane')
                        ->requiresConfirmation()
                        ->modalHeading('Kirim agenda yang dicentang ke grup WhatsApp sesuai pilihan grup pada agenda?')
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

                            $success = false;
                            $messages = [];

                            foreach ($records as $record) {
                                $groupIds = $record->groups?->pluck('id')
                                    ->filter()
                                    ->unique()
                                    ->values()
                                    ->all() ?? [];

                                if (empty($groupIds)) {
                                    $messages[] = ($record->nama_kegiatan ?? 'Agenda') . ': tidak ada grup tujuan';
                                    continue;
                                }

                                $result = $waGateway->sendAgendaToGroups($record, $groupIds);

                                if ($result['success'] ?? false) {
                                    $success = true;
                                    $sentGroups = $record->groups
                                        ->whereIn('id', array_keys($result['results'] ?? []))
                                        ->pluck('nama')
                                        ->filter()
                                        ->implode(', ');

                                    $messages[] = ($record->nama_kegiatan ?? 'Agenda') . ': terkirim ke ' . ($sentGroups ?: 'grup terpilih');
                                } else {
                                    $messages[] = ($record->nama_kegiatan ?? 'Agenda') . ': gagal (cek token/ID grup)';
                                }
                            }

                            $body = implode("\n", $messages);

                            if ($success) {
                                Notification::make()
                                    ->title('Selesai')
                                    ->body($body)
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
                        ->tooltip('Hanya agenda yang dicentang yang dikirim ke grup WA sesuai pilihan grup pada form agenda.'),
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
            ]);
    }
}
