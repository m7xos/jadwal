<?php

namespace App\Filament\Resources\FollowUpReminderLogs;

use App\Filament\Resources\FollowUpReminderLogs\Pages\ListFollowUpReminderLogs;
use App\Models\FollowUpReminder;
use App\Support\RoleAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use UnitEnum;

class FollowUpReminderLogResource extends Resource
{
    protected static ?string $model = FollowUpReminder::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Log Pengingat Lain';
    protected static ?string $pluralModelLabel = 'Log Pengingat Lain';
    protected static ?string $modelLabel = 'Log Pengingat Lain';
    protected static ?string $slug = 'follow-up-reminder-logs';
    protected static string|UnitEnum|null $navigationGroup = 'Log';
    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reminder_code')
                    ->label('Kode')
                    ->sortable(),
                Tables\Columns\TextColumn::make('nama_kegiatan')
                    ->label('Nama Kegiatan')
                    ->limit(40)
                    ->wrap()
                    ->searchable(),
                Tables\Columns\TextColumn::make('personil.nama')
                    ->label('Personil')
                    ->formatStateUsing(function ($state, FollowUpReminder $record) {
                        $nama = trim((string) ($record->personil->nama ?? ''));
                        $jabatan = trim((string) ($record->personil->jabatan ?? ''));

                        if ($nama === '' && $jabatan === '') {
                            return '-';
                        }

                        return $jabatan !== '' ? "{$nama} - {$jabatan}" : $nama;
                    })
                    ->searchable(query: function ($query, string $search) {
                        return $query->whereHas('personil', function ($q) use ($search) {
                            $q->where('nama', 'like', "%{$search}%")
                                ->orWhere('jabatan', 'like', "%{$search}%");
                        });
                    }),
                Tables\Columns\TextColumn::make('no_wa')
                    ->label('No. WA')
                    ->toggleable()
                    ->copyable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => $state === 'acknowledged' ? 'Sudah TL' : 'Belum TL')
                    ->colors([
                        'success' => 'acknowledged',
                        'warning' => 'pending',
                    ])
                    ->icons([
                        'acknowledged' => 'heroicon-m-check-badge',
                        'pending' => 'heroicon-m-bell-alert',
                    ]),
                Tables\Columns\TextColumn::make('send_via')
                    ->label('Kirim')
                    ->formatStateUsing(fn ($state) => $state === 'group' ? 'Grup' : 'Japri'),
                Tables\Columns\TextColumn::make('group.nama')
                    ->label('Grup WA')
                    ->toggleable()
                    ->limit(20),
                Tables\Columns\TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->date('d M Y'),
                Tables\Columns\TextColumn::make('jam')
                    ->label('Jam')
                    ->formatStateUsing(function ($state) {
                        if (! $state) {
                            return '-';
                        }

                        try {
                            return Carbon::parse($state)->format('H:i');
                        } catch (\Throwable) {
                            return $state;
                        }
                    }),
                Tables\Columns\TextColumn::make('last_sent_at')
                    ->label('Terakhir Kirim')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('acknowledged_at')
                    ->label('Selesai')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'acknowledged' => 'Selesai',
                    ]),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return RoleAccess::canSeeNav(auth()->user(), 'filament.admin.resources.follow-up-reminder-logs');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFollowUpReminderLogs::route('/'),
        ];
    }
}
