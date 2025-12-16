<?php

namespace App\Filament\Resources\Groups\Schemas;

use App\Models\Personil;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
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

                        Select::make('personils')
                            ->label('Personil dalam grup ini')
                            ->relationship('personils', 'nama')
                            ->getOptionLabelFromRecordUsing(function (Personil $record): string {
                                $nama = trim((string) ($record->nama ?? ''));
                                $jabatan = trim((string) ($record->jabatan ?? ''));

                                if ($jabatan === '') {
                                    return $nama;
                                }

                                return "{$nama} - {$jabatan}";
                            })
                            ->getSearchResultsUsing(function (string $search): array {
                                $searchTerm = trim($search);

                                return Personil::query()
                                    ->when(
                                        $searchTerm !== '',
                                        fn ($query) => $query->where(function ($q) use ($searchTerm) {
                                            $q->where('nama', 'like', "%{$searchTerm}%")
                                                ->orWhere('jabatan', 'like', "%{$searchTerm}%");
                                        })
                                    )
                                    ->orderBy('nama')
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(function (Personil $personil) {
                                        $nama = trim((string) ($personil->nama ?? ''));
                                        $jabatan = trim((string) ($personil->jabatan ?? ''));

                                        if ($jabatan === '') {
                                            return [$personil->getKey() => $nama];
                                        }

                                        return [$personil->getKey() => "{$nama} - {$jabatan}"];
                                    })
                                    ->all();
                            })
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->helperText('Pilih personil yang masuk grup ini untuk memudahkan penandaan/pengiriman WA.'),

                        Textarea::make('keterangan')
                            ->label('Keterangan')
                            ->rows(3),
                    ])
                    ->columns(1),
            ]);
    }
}
