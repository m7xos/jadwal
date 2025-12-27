<?php

namespace App\Filament\Resources\SuratKeputusanResource\Pages;

use App\Filament\Resources\SuratKeputusanResource;
use App\Models\KodeSurat;
use App\Services\SuratKeputusanService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;

class ListSuratKeputusans extends ListRecords
{
    protected static string $resource = SuratKeputusanResource::class;

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
                        ->label('Nomor SK')
                        ->numeric()
                        ->required()
                        ->helperText('Isi nomor yang ingin dibooking (contoh: 100).'),
                    DatePicker::make('booked_at')
                        ->label('Tanggal Booking')
                        ->required()
                        ->default(now())
                        ->native(false)
                        ->displayFormat('d/m/Y'),
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
                            ->title('Nomor SK tidak valid')
                            ->danger()
                            ->send();
                        return;
                    }

                    /** @var SuratKeputusanService $service */
                    $service = app(SuratKeputusanService::class);

                    try {
                        $service->createBooking($kode, $tahun, $nomor, [
                            'booked_at' => $data['booked_at'] ?? now(),
                            'source' => 'manual',
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
