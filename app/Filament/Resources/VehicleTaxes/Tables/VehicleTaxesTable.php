<?php

namespace App\Filament\Resources\VehicleTaxes\Tables;

use App\Models\VehicleTax;
use App\Services\VehicleTaxReminderService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VehicleTaxesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                BadgeColumn::make('jenis_kendaraan')
                    ->label('Jenis')
                    ->colors([
                        'primary' => 'mobil',
                        'success' => 'motor',
                    ])
                    ->icons([
                        'heroicon-m-truck' => 'mobil',
                        'heroicon-m-bolt' => 'motor',
                    ])
                    ->formatStateUsing(fn (string $state) => ucfirst($state)),

                TextColumn::make('plat_nomor')
                    ->label('Plat Nomor')
                    ->formatStateUsing(fn ($state) => strtoupper((string) $state))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('personil.nama')
                    ->label('Pemegang')
                    ->wrap()
                    ->searchable(),

                TextColumn::make('personil.no_wa')
                    ->label('No. WA Pemegang')
                    ->toggleable()
                    ->wrap(),

                TextColumn::make('tgl_pajak_tahunan')
                    ->label('Pajak Tahunan')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('tgl_pajak_lima_tahunan')
                    ->label('Pajak 5 Tahunan')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('last_tahunan_reminder_sent_at')
                    ->label('Terakhir 1 Tahunan')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('last_lima_tahunan_reminder_sent_at')
                    ->label('Terakhir 5 Tahunan')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('tgl_pajak_tahunan', 'asc')
            ->filters([
                Filter::make('jatuh_tempo_hari_ini')
                    ->label('Jatuh Tempo Hari Ini')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereDate('tgl_pajak_tahunan', today())
                        ->orWhereDate('tgl_pajak_lima_tahunan', today())
                    )
                    ->toggle(),
            ])
            ->recordActions([
                Action::make('kirim_pengingat')
                    ->label('Kirim Pengingat')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->form([
                        \Filament\Forms\Components\Select::make('tipe')
                            ->label('Jenis Pajak')
                            ->options([
                                'tahunan' => '1 Tahunan',
                                'lima_tahunan' => '5 Tahunan',
                            ])
                            ->default('tahunan')
                            ->required(),
                    ])
                    ->action(function (VehicleTax $record, array $data) {
                        /** @var VehicleTaxReminderService $reminder */
                        $reminder = app(VehicleTaxReminderService::class);

                        $result = $reminder->send($record, $data['tipe'] ?? 'tahunan');

                        if ($result['success'] ?? false) {
                            Notification::make()
                                ->title('Pengingat dikirim')
                                ->body('Pesan terkirim ke pemegang dan pengurus barang.')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Pengingat gagal dikirim')
                                ->body($result['error'] ?? 'Coba lagi nanti.')
                                ->danger()
                                ->send();
                        }
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
