<?php

namespace App\Filament\Resources\Personils\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;

class PersonilForm
{
    public static function configure(Schema $schema): Schema
    {
        $kategoriOptions = [
            'kecamatan' => 'Personil Kecamatan',
            'kelurahan' => 'Personil Kelurahan',
            'kades_lurah' => 'Personil Kades/Lurah',
            'sekdes_admin' => 'Personil Sekdes/Selur/Admin',
        ];

        return $schema
            ->components([
                Section::make('Data Personil')
                    ->schema([
                        TextInput::make('nama')
                            ->label('Nama')
                            ->required()
                            ->maxLength(255),
						
						TextInput::make('nip')
							->label('NIP (dipakai untuk login)')
							->maxLength(30)
							->required()
							->unique(table: \App\Models\Personil::class, column: 'nip', ignoreRecord: true),

                        TextInput::make('jabatan')
                            ->label('Jabatan')
                            ->maxLength(255),

                        Select::make('kategori')
                            ->label('Kategori')
                            ->options($kategoriOptions)
                            ->native(false)
                            ->searchable()
                            ->placeholder('Pilih kategori personil')
                            ->helperText('Kategori dipakai untuk filter pengiriman pesan.'),

                        TextInput::make('no_wa')
                            ->label('Nomor WA')
                            ->placeholder('Contoh: 6281234567890')
                            ->required()
                            ->maxLength(20)
                            ->helperText('Nomor WA ini juga dipakai sebagai password login.'),

                        Textarea::make('keterangan')
                            ->label('Keterangan')
                            ->rows(3),

                        Select::make('groups')
                            ->label('Grup WhatsApp')
                            ->relationship('groups', 'nama')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->helperText('Pilih grup WA tempat personil ini terdaftar.'),
                    ])
                    ->columns(2),
            ]);
    }
}
