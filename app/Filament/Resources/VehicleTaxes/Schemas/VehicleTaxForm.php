<?php

namespace App\Filament\Resources\VehicleTaxes\Schemas;

use App\Models\Personil;
use App\Models\VehicleAsset;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class VehicleTaxForm
{
    protected static function guessJenisKendaraan(VehicleAsset $asset): string
    {
        $namaAset = strtolower((string) $asset->nama_aset);

        if (str_contains($namaAset, 'motor')) {
            return 'motor';
        }

        return 'mobil';
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Data Kendaraan Dinas')
                    ->schema([
                        Hidden::make('jenis_kendaraan')
                            ->default(null),
                      
                        Select::make('plat_nomor')
                            ->label('Plat Nomor')
                            ->options(fn () => VehicleAsset::query()
                                ->orderBy('nomor_polisi')
                                ->pluck('nomor_polisi', 'nomor_polisi'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if (! $state) {
                                    $set('jenis_kendaraan', null);
                                    $set('jenis_kendaraan_display', null);
                                    $set('asset_info', null);

                                    return;
                                }

                                $asset = VehicleAsset::query()
                                    ->where('nomor_polisi', $state)
                                    ->first();

                                if ($asset) {
                                    $jenis = static::guessJenisKendaraan($asset);
                                    $set('jenis_kendaraan', $jenis);
                                    $set('jenis_kendaraan_display', ucfirst($jenis));
                                    $set('asset_info', trim(($asset->merk_type ?? '') . ' ' . ($asset->nama_aset ?? '')));
                                } else {
                                    $set('jenis_kendaraan', null);
                                    $set('jenis_kendaraan_display', null);
                                    $set('asset_info', null);
                                }
                            }),

                        TextInput::make('jenis_kendaraan_display')
                            ->label('Jenis Kendaraan')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Otomatis dari data aset'),

                        TextInput::make('asset_info')
                            ->label('Info Aset')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Otomatis dari data aset'),

                        Select::make('personil_id')
                            ->label('Pemegang Kendaraan (Personil)')
                            ->relationship('personil', 'nama')
                            ->preload()
                            ->searchable()
                            ->required()
                            ->helperText('Nomor WA pemegang otomatis diambil dari data personil.'),

                        Placeholder::make('pemegang_no_wa_info')
                            ->label('Nomor WA Pemegang')
                            ->content(function (Get $get) {
                                $personilId = $get('personil_id');

                                if (! $personilId) {
                                    return 'Pilih personil untuk melihat nomor WA.';
                                }

                                $personil = Personil::query()->find($personilId);

                                return $personil?->no_wa ?? '-';
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Jadwal Pajak')
                    ->schema([
                        DatePicker::make('tgl_pajak_tahunan')
                            ->label('Tanggal Pajak Tahunan')
                            ->required()
                            ->displayFormat('d-m-Y'),

                        DatePicker::make('tgl_pajak_lima_tahunan')
                            ->label('Tanggal Pajak 5 Tahunan')
                            ->required()
                            ->displayFormat('d-m-Y'),
                    ])
                    ->columns(2),
            ]);
    }
}
