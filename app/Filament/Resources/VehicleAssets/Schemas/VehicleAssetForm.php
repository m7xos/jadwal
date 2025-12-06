<?php

namespace App\Filament\Resources\VehicleAssets\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class VehicleAssetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Data Kendaraan')
                    ->schema([
                        TextInput::make('nama_aset')
                            ->label('Nama Aset')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('merk_type')
                            ->label('Merk / Tipe')
                            ->maxLength(255),
                        TextInput::make('nomor_polisi')
                            ->label('Nomor Polisi')
                            ->required()
                            ->maxLength(50),
                        TextInput::make('nomor_bpkb')
                            ->label('Nomor BPKB')
                            ->maxLength(100),
                        TextInput::make('ukuran_cc')
                            ->label('Ukuran / CC')
                            ->maxLength(50),
                        TextInput::make('bahan')
                            ->label('Bahan')
                            ->maxLength(50),
                        DatePicker::make('tahun')
                            ->label('Tahun')
                            ->displayFormat('Y-m-d'),
                        TextInput::make('harga')
                            ->label('Harga')
                            ->numeric()
                            ->prefix('Rp'),
                        TextInput::make('kode_aset')
                            ->label('Kode Aset')
                            ->maxLength(100),
                        TextInput::make('kode_upb')
                            ->label('Kode UPB')
                            ->maxLength(100),
                        TextInput::make('nama_upb')
                            ->label('Nama UPB')
                            ->maxLength(255),
                        TextInput::make('id_pemda')
                            ->label('ID Pemda')
                            ->maxLength(100),
                        TextInput::make('reg')
                            ->label('Reg')
                            ->maxLength(50),
                        TextInput::make('nomor_pabrik')
                            ->label('Nomor Pabrik')
                            ->maxLength(100),
                        TextInput::make('nomor_rangka')
                            ->label('Nomor Rangka')
                            ->maxLength(100),
                        TextInput::make('nomor_mesin')
                            ->label('Nomor Mesin')
                            ->maxLength(100),
                        TextInput::make('keterangan')
                            ->label('Keterangan')
                            ->maxLength(255),
                    ])
                    ->columns(2),
            ]);
    }
}
