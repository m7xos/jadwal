<?php

namespace App\Filament\Resources\VehicleAssets\Tables;

use App\Services\SuratKuasaPajakGenerator;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class VehicleAssetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nomor_polisi')
                    ->label('No. Polisi')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('merk_type')
                    ->label('Merk / Tipe')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('nama_aset')
                    ->label('Nama Aset')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('tahun')
                    ->label('Tahun')
                    ->date('Y-m-d')
                    ->sortable(),
                TextColumn::make('ukuran_cc')
                    ->label('CC')
                    ->toggleable(),
                TextColumn::make('harga')
                    ->label('Harga')
                    ->money('idr', locale: 'id')
                    ->sortable(),
                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->limit(40)
                    ->tooltip(fn ($state) => $state),
            ])
            ->defaultSort('nomor_polisi')
            ->recordActions([])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('edit_selected')
                        ->label('Edit')
                        ->icon('heroicon-o-pencil-square')
                        ->color('primary')
                        ->form([
                            Forms\Components\TextInput::make('nama_aset')
                                ->label('Nama Aset')
                                ->maxLength(255),
                            Forms\Components\TextInput::make('merk_type')
                                ->label('Merk / Tipe')
                                ->maxLength(255),
                            Forms\Components\TextInput::make('nomor_polisi')
                                ->label('Nomor Polisi')
                                ->maxLength(50),
                            Forms\Components\DatePicker::make('tahun')
                                ->label('Tahun')
                                ->displayFormat('Y-m-d'),
                            Forms\Components\TextInput::make('harga')
                                ->label('Harga')
                                ->numeric(),
                            Forms\Components\TextInput::make('keterangan')
                                ->label('Keterangan')
                                ->maxLength(255),
                        ])
                        ->fillForm(function (Collection $records): array {
                            /** @var \App\Models\VehicleAsset|null $first */
                            $first = $records->first();

                            if (! $first) {
                                return [];
                            }

                            return [
                                'nama_aset' => $first->nama_aset,
                                'merk_type' => $first->merk_type,
                                'nomor_polisi' => $first->nomor_polisi,
                                'tahun' => optional($first->tahun)->toDateString(),
                                'harga' => $first->harga,
                                'keterangan' => $first->keterangan,
                            ];
                        })
                        ->action(function (Collection $records, array $data): void {
                            $payload = array_filter($data, static fn ($value) => $value !== null && $value !== '');

                            if (empty($payload)) {
                                return;
                            }

                            foreach ($records as $record) {
                                $record->fill($payload);
                                $record->save();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('surat_kuasa')
                        ->label('Surat Kuasa Membayar Pajak')
                        ->icon('heroicon-o-document-text')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            if ($records->isEmpty()) {
                                Notification::make()
                                    ->title('Tidak ada data yang dipilih')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            /** @var SuratKuasaPajakGenerator $generator */
                            $generator = app(SuratKuasaPajakGenerator::class);

                            try {
                                $result = $generator->generate(
                                    $records->pluck('nomor_polisi')->filter()->all()
                                );
                            } catch (\Throwable $th) {
                                Log::error('Gagal generate surat kuasa pajak', [
                                    'error' => $th->getMessage(),
                                ]);

                                Notification::make()
                                    ->title('Gagal membuat surat kuasa')
                                    ->body($th->getMessage())
                                    ->danger()
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->title('Surat kuasa siap diunduh')
                                ->body('File siap diunduh.')
                                ->success()
                                ->send();

                            return response()
                                ->download($result['path'])
                                ->deleteFileAfterSend(true);
                        })
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
