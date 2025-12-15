<?php

namespace App\Filament\Resources\Personils\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PersonilsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')
                    ->label('Nama')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                /**
                TextColumn::make('nip')
                    ->label('NIP')
                    ->searchable(),
				*/	
                TextColumn::make('jabatan')
                    ->label('Jabatan')
                    ->searchable()
                    ->sortable(),

               /** TextColumn::make('pangkat')
                    ->label('Pangkat')
                    ->toggleable()
                    ->wrap(),

                TextColumn::make('golongan')
                    ->label('Golongan')
                    ->toggleable()
                    ->wrap(),
                */
                TextColumn::make('kategori')
                    ->label('Kategori')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'kecamatan' => 'Personil Kecamatan',
                        'kelurahan' => 'Personil Kelurahan',
                        'kades_lurah' => 'Personil Kades/Lurah',
                        'sekdes_admin' => 'Personil Sekdes/Selur/Admin',
                        default => $state,
                    })
                    ->sortable(),

                TextColumn::make('no_wa')
                    ->label('Nomor WA')
                    ->searchable(),

              /**  TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->limit(40)
                    ->tooltip(fn ($state) => $state),
                */
            ])
            ->filters([
                SelectFilter::make('kategori')
                    ->label('Kategori')
                    ->options([
                        'kecamatan' => 'Personil Kecamatan',
                        'kelurahan' => 'Personil Kelurahan',
                        'kades_lurah' => 'Personil Kades/Lurah',
                        'sekdes_admin' => 'Personil Sekdes/Selur/Admin',
                    ]),
            ])
            // ==== AKSI PER BARIS (EDIT DLL) ====
            ->recordActions([
                EditAction::make(), // edit dalam modal / halaman edit resource
                // Kalau mau tambah action lain di sini juga bisa
            ])
            // ==== AKSI TOOLBAR (BULK DELETE DLL) ====
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
