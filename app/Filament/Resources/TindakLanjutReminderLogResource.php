<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TindakLanjutReminderLogResource\Pages;
use App\Models\TindakLanjutReminderLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

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
