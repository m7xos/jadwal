<?php

namespace App\Filament\Resources\Kegiatans\Tables;

use App\Models\Kegiatan;
use App\Services\WablasService;
use Filament\Actions\Action;
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

                TextColumn::make('jenis_surat')
                    ->label('Jenis Surat')
                    ->formatStateUsing(function (string $state): string {
                        return match ($state) {
                            'tindak_lanjut' => 'Surat Masuk (TL)',
                            default => 'Surat Undangan',
                        };
                    })
                    ->badge()
                    ->colors([
                        'warning' => 'tindak_lanjut',
                        'primary' => 'undangan',
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
                    ->visible(fn (?Kegiatan $record) => $record?->jenis_surat === 'tindak_lanjut'),

                TextColumn::make('tindak_lanjut_selesai_at')
                    ->label('Status TL')
                    ->state(function (Kegiatan $record): ?string {
                        if ($record->jenis_surat !== 'tindak_lanjut') {
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
                    ->visible(fn ($record) => $record === null || $record?->jenis_surat === 'tindak_lanjut'),
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

                Filter::make('tindak_lanjut')
                    ->label('Surat Masuk (TL)')
                    ->query(fn (Builder $query): Builder => $query->where('jenis_surat', 'tindak_lanjut')),
            ])

            // ================== AKSI PER RECORD ==================
          
            ->recordActions([
                EditAction::make(),

                DeleteAction::make(),
                /**
                // Kirim 1 kegiatan ke grup WA
                Action::make('kirim_wa_grup')
                    ->label('Kirim WA Grup')
                    ->icon('heroicon-o-paper-airplane')
                    ->requiresConfirmation()
                    ->modalHeading('Kirim agenda ini ke grup WhatsApp?')
                    ->action(function (Kegiatan $record) {
                        
                        $wablas = app(WablasService::class);

                        $record->loadMissing('personils');

                        $success = $wablas->sendGroupRekap(collect([$record]));

                        if ($success) {
                            Notification::make()
                                ->title('Berhasil')
                                ->body('Agenda berhasil dikirim ke grup WhatsApp.')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Gagal')
                                ->body('Gagal mengirim pesan ke Wablas. Cek konfigurasi dan status device.')
                                ->danger()
                                ->send();
                        }
                    }),
                    **/

                // Kirim ke WA semua personil 1 kegiatan
                Action::make('kirim_wa_personil')
                    ->label('Kirim WA Personil')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->requiresConfirmation()
                    ->modalHeading('Kirim agenda ini ke WA personil yang hadir?')
                    ->action(function (Kegiatan $record) {
                        /** @var WablasService $wablas */
                        $wablas = app(WablasService::class);

                        $record->loadMissing('personils');

                        $success = $wablas->sendToPersonils($record);

                        if ($success) {
                            Notification::make()
                                ->title('Berhasil')
                                ->body('Agenda berhasil dikirim ke WA seluruh personil yang hadir.')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Gagal')
                                ->body('Gagal mengirim ke WA personil. Pastikan nomor WA terisi dan konfigurasi Wablas benar.')
                                ->danger()
                                ->send();
                        }
                    }),
            ])

            // ================== BULK ACTION (SESUAI FILTER) ==================
            ->toolbarActions([
                BulkActionGroup::make([
                    // Bulk: kirim semua yang SEDANG tampil (sesuai filter/search/sort)
                    BulkAction::make('kirim_wa_rekap_terfilter')
                        ->label('Kirim Rekap Disposisi (Sesuai Filter)')
                        ->icon('heroicon-o-paper-airplane')
                        ->requiresConfirmation()
                        ->modalHeading('Kirim rekap semua agenda yang sedang tampil (berdasarkan filter) ke grup WhatsApp?')
                        ->action(function (array $data, $livewire) {
                            // Ambil query yang sudah ter-filter + ter-sort + ter-search
                            if (! method_exists($livewire, 'getFilteredSortedTableQuery')) {
                                Notification::make()
                                    ->title('Gagal')
                                    ->body('Tidak dapat mengambil data terfilter dari tabel.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $query = clone $livewire->getFilteredSortedTableQuery();
                            /** @var Collection $records */
                            $records = $query->get();

                            if ($records->isEmpty()) {
                                Notification::make()
                                    ->title('Tidak ada data')
                                    ->body('Tidak ada agenda yang cocok dengan filter saat ini.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $records->load('personils');

                            /** @var WablasService $wablas */
                            $wablas = app(WablasService::class);

                            $success = $wablas->sendGroupRekap($records);

                            if ($success) {
                                Notification::make()
                                    ->title('Berhasil')
                                    ->body('Rekap semua agenda yang terfilter berhasil dikirim ke grup WhatsApp.')
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Gagal')
                                    ->body('Gagal mengirim rekap ke Wablas. Cek konfigurasi dan koneksi device.')
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion()
                        ->tooltip('Gunakan semua data yang sedang tampil di tabel (berdasarkan filter & pencarian).'),
                     //baru   ,
					BulkAction::make('kirim_wa_belum_disposisi')
					->label('Mohon Disposisi (Filter)')
					->icon('heroicon-o-paper-airplane')
					->requiresConfirmation()
					->modalHeading('Kirim ke grup WhatsApp daftar agenda yang belum disposisi (berdasarkan filter saat ini)?')
					->action(function (array $data, $livewire) {
						if (! method_exists($livewire, 'getFilteredSortedTableQuery')) {
							Notification::make()
								->title('Gagal')
								->body('Tidak dapat mengambil data terfilter dari tabel.')
								->danger()
								->send();

							return;
						}

						/** @var Builder $query */
						$query = clone $livewire->getFilteredSortedTableQuery();

						// Hanya ambil yang BELUM disposisi
						$query->where('sudah_disposisi', false);

						/** @var Collection $records */
						$records = $query->get();

						if ($records->isEmpty()) {
							Notification::make()
								->title('Tidak ada data')
								->body('Tidak ada agenda berstatus belum disposisi pada filter saat ini.')
								->warning()
								->send();

							return;
						}

						$records->load('personils'); // tidak wajib, tapi kalau mau pakai nanti aman

						/** @var WablasService $wablas */
						$wablas = app(WablasService::class);

						$success = $wablas->sendGroupBelumDisposisi($records);

						if ($success) {
							Notification::make()
								->title('Berhasil')
								->body('Daftar agenda yang belum disposisi berhasil dikirim ke grup WhatsApp.')
								->success()
								->send();
						} else {
							Notification::make()
								->title('Gagal')
								->body('Gagal mengirim pesan ke Wablas. Cek konfigurasi/token/ID grup.')
								->danger()
								->send();
						}
					})
					->deselectRecordsAfterCompletion()
                    ->tooltip('Mengirim ke grup WA daftar agenda yang belum disposisi, berdasarkan filter & pencarian saat ini.'),

                    BulkAction::make('kirim_wa_multi_grup')
                        ->label('Kirim WA Multi Grup')
                        ->icon('heroicon-o-paper-airplane')
                        ->requiresConfirmation()
                        ->modalHeading('Kirim agenda terpilih ke grup WhatsApp yang dipilih di form tiap agenda?')
                        ->action(function (Collection $records) {
                            if ($records->isEmpty()) {
                                Notification::make()
                                    ->title('Tidak ada data')
                                    ->body('Pilih minimal satu agenda terlebih dahulu.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            /** @var WablasService $wablas */
                            $wablas = app(WablasService::class);

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

                                $result = $wablas->sendAgendaToGroups($record, $groupIds);

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
                        ->tooltip('Kirim agenda terpilih ke grup WA sesuai pilihan grup pada form agenda.'),

                    DeleteBulkAction::make(),
                ]),	
            ]);
    }
}
