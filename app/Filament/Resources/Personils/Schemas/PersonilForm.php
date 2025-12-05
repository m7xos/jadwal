<?php

namespace App\Filament\Resources\Personils\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PersonilForm
{
    public static function configure(Schema $schema): Schema
    {
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

                        TextInput::make('no_wa')
                            ->label('Nomor WA')
                            ->placeholder('Contoh: 6281234567890')
                            ->required()
                            ->maxLength(20)
                            ->helperText('Nomor WA ini juga dipakai sebagai password login.'),

                        Textarea::make('keterangan')
                            ->label('Keterangan')
                            ->rows(3),
                    ])
                    ->columns(2),
            ]);
    }
}
