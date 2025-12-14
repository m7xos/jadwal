<?php

namespace App\Filament\Resources\Groups\Schemas;

use Filament\Forms\Components\Get;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Data Grup')
                    ->schema([
                        TextInput::make('nama')
                            ->label('Nama Grup')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('wablas_group_id')
                            ->label('ID Grup Wablas')
                            ->helperText('Isi dengan ID grup dari Wablas (1203xxxxxxxxxx).')
                            ->required(fn (Get $get) => (bool) $get('is_default'))
                            ->maxLength(255),

                        Toggle::make('is_default')
                            ->label('Jadikan grup default')
                            ->helperText('Dipakai sebagai tujuan utama ketika mengirim pesan ke satu grup. Hanya satu grup bisa menjadi default.')
                            ->inline(false),

                        Textarea::make('keterangan')
                            ->label('Keterangan')
                            ->rows(3),
                    ])
                    ->columns(1),
            ]);
    }
}
