<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TindakLanjutReminderLogResource\Pages;
use App\Models\TindakLanjutReminderLog;
use App\Services\WablasService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class TindakLanjutReminderLogResource extends Resource
{
    protected static ?string $model = TindakLanjutReminderLog::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-ellipsis';

    protected static ?string $navigationLabel = 'Log Pengingat TL';

    protected static ?string $slug = 'tindak-lanjut-reminder-logs';

    protected static ?int $navigationSort = 8;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kegiatan.nomor')
                    ->label('Nomor Surat')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('kegiatan.nama_kegiatan')
                    ->label('Perihal')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('kegiatan.batas_tindak_lanjut')
                    ->label('Batas TL')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                BadgeColumn::make('status')
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
                    ->label('Status')
                    ->sortable(),
                TextColumn::make('error_message')
                    ->label('Error')
                    ->toggleable()
                    ->wrap()
                    ->limit(60),
                TextColumn::make('sent_at')
                    ->label('Waktu Kirim')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
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
                    ->modalHeading('Respons Wablas')
                    ->modalSubheading('Jawaban lengkap dari API Wablas saat pengiriman pengingat.')
                    ->modalContent(fn (TindakLanjutReminderLog $record) => view('filament.reminder-log-response', [
                        'response' => $record->response,
                    ]))
                    ->hidden(fn (TindakLanjutReminderLog $record) => empty($record->response)),
                Action::make('resend')
                    ->label('Kirim Ulang')
                    ->icon('heroicon-m-arrow-path')
                    ->requiresConfirmation()
                    ->visible(fn (TindakLanjutReminderLog $record) => $record->status !== 'success')
                    ->action(function (TindakLanjutReminderLog $record) {
                        $kegiatan = $record->kegiatan;

                        if (! $kegiatan) {
                            Notification::make()
                                ->title('Kegiatan tidak ditemukan')
                                ->danger()
                                ->send();

                            return;
                        }

                        /** @var WablasService $wablas */
                        $wablas = app(WablasService::class);

                        if (! $wablas->isConfigured()) {
                            Notification::make()
                                ->title('Konfigurasi Wablas belum lengkap')
                                ->danger()
                                ->send();

                            return;
                        }

                        $result = $wablas->sendGroupTindakLanjutReminder($kegiatan);
                        $success = (bool) ($result['success'] ?? false);

                        $record->status = $success ? 'success' : 'failed';
                        $record->error_message = $result['error'] ?? null;
                        $record->response = $result['response'] ?? null;
                        $record->sent_at = $success ? Carbon::now() : $record->sent_at;
                        $record->save();

                        if ($success) {
                            $kegiatan->forceFill(['tl_reminder_sent_at' => Carbon::now()])->save();

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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTindakLanjutReminderLogs::route('/'),
        ];
    }
}
