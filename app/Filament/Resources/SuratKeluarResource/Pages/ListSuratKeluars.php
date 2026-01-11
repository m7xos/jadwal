<?php

namespace App\Filament\Resources\SuratKeluarResource\Pages;

use App\Filament\Resources\SuratKeluarResource;
use App\Models\KodeSurat;
use App\Models\Personil;
use App\Services\SuratKeluarService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class ListSuratKeluars extends ListRecords
{
    protected static string $resource = SuratKeluarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('booking_nomor')
                ->label('Booking Nomor')
                ->icon('heroicon-o-bookmark')
                ->form([
                    Select::make('kode_surat_id')
                        ->label('Kode Klasifikasi')
                        ->options(fn () => KodeSurat::query()
                            ->orderBy('kode')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn (KodeSurat $kode) => [
                                $kode->id => $kode->kode . ' - ' . $kode->keterangan,
                            ])
                            ->all())
                        ->getSearchResultsUsing(function (string $search): array {
                            $search = trim($search);

                            return KodeSurat::query()
                                ->when($search !== '', function ($query) use ($search) {
                                    $query->where(function ($builder) use ($search) {
                                        $builder
                                            ->where('kode', 'like', '%' . $search . '%')
                                            ->orWhere('keterangan', 'like', '%' . $search . '%');
                                    });
                                })
                                ->orderBy('kode')
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn (KodeSurat $kode) => [
                                    $kode->id => $kode->kode . ' - ' . $kode->keterangan,
                                ])
                                ->all();
                        })
                        ->getOptionLabelUsing(function ($value): ?string {
                            if (! $value) {
                                return null;
                            }

                            $kode = KodeSurat::find($value);
                            if (! $kode) {
                                return null;
                            }

                            return $kode->kode . ' - ' . $kode->keterangan;
                        })
                        ->searchable()
                        ->preload()
                        ->placeholder('Ketik kode atau keterangan')
                        ->required(),
                    TextInput::make('tahun')
                        ->label('Tahun')
                        ->numeric()
                        ->required()
                        ->default(now()->year),
                    TextInput::make('nomor_urut')
                        ->label('Nomor Surat')
                        ->numeric()
                        ->required()
                        ->helperText('Isi nomor yang ingin dibooking (contoh: 100).'),
                    DatePicker::make('booked_at')
                        ->label('Tanggal Booking')
                        ->required()
                        ->default(now())
                        ->native(false),
                    Select::make('requested_by_personil_id')
                        ->label('Akronim Jabatan')
                        ->options(fn () => Personil::query()
                            ->whereNotNull('jabatan_akronim')
                            ->where('jabatan_akronim', '!=', '')
                            ->orderBy('jabatan_akronim')
                            ->limit(100)
                            ->get()
                            ->mapWithKeys(function (Personil $personil) {
                                $akronim = trim((string) ($personil->jabatan_akronim ?? ''));
                                $nama = trim((string) ($personil->nama ?? ''));

                                if ($nama !== '') {
                                    return [$personil->id => $akronim . ' - ' . $nama];
                                }

                                return [$personil->id => $akronim];
                            })
                            ->all())
                        ->getSearchResultsUsing(function (string $search): array {
                            $term = trim($search);

                            return Personil::query()
                                ->whereNotNull('jabatan_akronim')
                                ->where('jabatan_akronim', '!=', '')
                                ->when($term !== '', function ($query) use ($term) {
                                    $query->where(function ($builder) use ($term) {
                                        $builder
                                            ->where('jabatan_akronim', 'like', "%{$term}%")
                                            ->orWhere('nama', 'like', "%{$term}%")
                                            ->orWhere('jabatan', 'like', "%{$term}%");
                                    });
                                })
                                ->orderBy('jabatan_akronim')
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(function (Personil $personil) {
                                    $akronim = trim((string) ($personil->jabatan_akronim ?? ''));
                                    $nama = trim((string) ($personil->nama ?? ''));

                                    if ($nama !== '') {
                                        return [$personil->id => $akronim . ' - ' . $nama];
                                    }

                                    return [$personil->id => $akronim];
                                })
                                ->all();
                        })
                        ->getOptionLabelUsing(function ($value): ?string {
                            if (! $value) {
                                return null;
                            }

                            $personil = Personil::find($value);
                            if (! $personil) {
                                return null;
                            }

                            $akronim = trim((string) ($personil->jabatan_akronim ?? ''));
                            if ($akronim === '') {
                                return null;
                            }

                            $nama = trim((string) ($personil->nama ?? ''));

                            if ($nama !== '') {
                                return $akronim . ' - ' . $nama;
                            }

                            return $akronim;
                        })
                        ->searchable()
                        ->preload()
                        ->placeholder('Pilih akronim')
                        ->helperText('Akronim akan ditambahkan di akhir nomor surat.')
                        ->default(function () {
                            $user = auth()->user();

                            if ($user?->isArsiparis() !== true) {
                                return null;
                            }

                            $akronim = trim((string) ($user->jabatan_akronim ?? ''));

                            return $akronim !== '' ? $user->id : null;
                        })
                        ->required(fn () => auth()->user()?->isArsiparis() === true)
                        ->visible(fn () => auth()->user()?->isArsiparis() === true),
                ])
                ->action(function (array $data) {
                    $kode = KodeSurat::find($data['kode_surat_id'] ?? null);
                    if (! $kode) {
                        Notification::make()
                            ->title('Kode klasifikasi tidak ditemukan')
                            ->danger()
                            ->send();
                        return;
                    }

                    $tahun = (int) ($data['tahun'] ?? now()->year);
                    $nomor = (int) ($data['nomor_urut'] ?? 0);

                    if ($nomor <= 0) {
                        Notification::make()
                            ->title('Nomor surat tidak valid')
                            ->danger()
                            ->send();
                        return;
                    }

                    /** @var SuratKeluarService $service */
                    $service = app(SuratKeluarService::class);
                    $user = auth()->user();
                    $requestedPersonilId = $user?->id;

                    if ($user?->isArsiparis() && ! empty($data['requested_by_personil_id'])) {
                        $requestedPersonilId = (int) $data['requested_by_personil_id'];
                    }

                    try {
                        $service->createBooking($kode, $tahun, $nomor, [
                            'booked_at' => $data['booked_at'] ?? now(),
                            'source' => 'manual',
                            'requested_by_personil_id' => $requestedPersonilId,
                        ]);
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Booking gagal')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                        return;
                    }

                    Notification::make()
                        ->title('Nomor berhasil dibooking')
                        ->success()
                        ->send();
                }),
        ];
    }
}
