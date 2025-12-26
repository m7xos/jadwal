<?php

namespace App\Filament\Resources\SuratKeluarResource\Pages;

use App\Filament\Resources\SuratKeluarResource;
use App\Models\KodeSurat;
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
                            ->get()
                            ->mapWithKeys(fn (KodeSurat $kode) => [
                                $kode->id => $kode->kode . ' - ' . $kode->keterangan,
                            ]))
                        ->searchable()
                        ->preload()
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
