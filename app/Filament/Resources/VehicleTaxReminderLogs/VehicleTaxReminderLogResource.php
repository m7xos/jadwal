<?php

namespace App\Filament\Resources\VehicleTaxReminderLogs;

use App\Filament\Resources\VehicleTaxReminderLogs\Pages\ListVehicleTaxReminderLogs;
use App\Models\VehicleTaxReminderLog;
use App\Services\VehicleTaxReminderService;
use App\Support\RoleAccess;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class VehicleTaxReminderLogResource extends Resource
{
    protected static ?string $model = VehicleTaxReminderLog::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-ellipsis';
    protected static ?string $navigationLabel = 'Log Pengingat Pajak';
    protected static ?string $modelLabel = 'Log Pengingat Pajak';
    protected static ?string $pluralModelLabel = 'Log Pengingat Pajak';
    protected static ?string $slug = 'vehicle-tax-reminder-logs';
    protected static string|UnitEnum|null $navigationGroup = 'Log';
    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('vehicleTax.plat_nomor')
                    ->label('No. Polisi')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Jenis')
                    ->formatStateUsing(fn (string $state) => $state === 'lima_tahunan' ? '5 tahunan' : '1 tahunan'),
                Tables\Columns\TextColumn::make('stage')
                    ->label('Tahap')
                    ->badge()
                    ->colors([
                        'warning' => fn ($state) => $state === 'H-7',
                        'info' => fn ($state) => $state === 'H-3',
                        'success' => fn ($state) => $state === 'H0',
                    ]),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'success',
                        'failed' => 'danger',
                        'pending' => 'warning',
                    ])
                    ->icons([
                        'success' => 'heroicon-m-check-badge',
                        'failed' => 'heroicon-m-x-circle',
                        'pending' => 'heroicon-m-clock',
                    ])
                    ->label('Status'),
                Tables\Columns\TextColumn::make('error_message')
                    ->label('Error')
                    ->wrap()
                    ->limit(60)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Waktu Kirim')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'success' => 'Berhasil',
                        'failed' => 'Gagal',
                        'pending' => 'Menunggu',
                    ]),
            ])
            ->actions([
                Action::make('lihat_response')
                    ->label('Detail Respons')
                    ->icon('heroicon-m-eye')
                    ->modalHeading('Respons WA Gateway')
                    ->modalContent(fn (VehicleTaxReminderLog $record) => view('filament.reminder-log-response', [
                        'response' => $record->response,
                    ]))
                    ->hidden(fn (VehicleTaxReminderLog $record) => empty($record->response)),
                Action::make('resend')
                    ->label('Kirim Ulang')
                    ->icon('heroicon-m-arrow-path')
                    ->requiresConfirmation()
                    ->visible(fn (VehicleTaxReminderLog $record) => $record->status !== 'success')
                    ->action(function (VehicleTaxReminderLog $record) {
                        $vehicle = $record->vehicleTax;

                        if (! $vehicle) {
                            Notification::make()
                                ->title('Data kendaraan tidak ditemukan')
                                ->danger()
                                ->send();

                            return;
                        }

                        /** @var VehicleTaxReminderService $reminder */
                        $reminder = app(VehicleTaxReminderService::class);

                        $result = $reminder->send(
                            $vehicle,
                            $record->type ?? 'tahunan',
                            $record->stage
                        );

                        $record->status = ($result['success'] ?? false) ? 'success' : 'failed';
                        $record->error_message = $result['error'] ?? null;
                        $record->response = $result['response'] ?? null;
                        $record->sent_at = ($result['success'] ?? false) ? now() : $record->sent_at;
                        $record->save();

                        if ($result['success'] ?? false) {
                            Notification::make()
                                ->title('Pengingat berhasil dikirim ulang')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Pengingat gagal dikirim ulang')
                                ->body($record->error_message ?? 'Coba lagi nanti.')
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return RoleAccess::canSeeNav(auth()->user(), 'filament.admin.resources.vehicle-tax-reminder-logs');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVehicleTaxReminderLogs::route('/'),
        ];
    }
}
