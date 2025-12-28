<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WaInboxMessageResource\Pages\EditWaInboxMessage;
use App\Filament\Resources\WaInboxMessageResource\Pages\ListWaInboxMessages;
use App\Models\Personil;
use App\Models\WaInboxMessage;
use App\Support\RoleAccess;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class WaInboxMessageResource extends Resource
{
    protected static ?string $model = WaInboxMessage::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'Chat Masuk WA';
    protected static ?string $pluralModelLabel = 'Chat Masuk WA';
    protected static ?string $modelLabel = 'Chat WA';
    protected static ?string $slug = 'wa-inbox-messages';
    protected static string|UnitEnum|null $navigationGroup = 'Layanan Publik';
    protected static ?int $navigationSort = 8;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Pesan Masuk')
                    ->schema([
                        TextInput::make('sender_number')
                            ->label('Nomor Pengirim')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('sender_name')
                            ->label('Nama Pengirim')
                            ->disabled()
                            ->dehydrated(false),
                        Placeholder::make('received_at')
                            ->label('Waktu Masuk')
                            ->content(fn (?WaInboxMessage $record) => $record?->received_at?->format('d/m/Y H:i') ?? '-'),
                        Textarea::make('message')
                            ->label('Pesan')
                            ->rows(4)
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Balasan')
                    ->schema([
                        Textarea::make('reply_message')
                            ->label('Balasan')
                            ->rows(4)
                            ->helperText(function () {
                                $nickname = static::signatureForUser(auth()->user());

                                return $nickname
                                    ? 'Tanda tangan otomatis: ' . $nickname
                                    : 'Tanda tangan otomatis akan ditambahkan.';
                            })
                            ->required(fn (?WaInboxMessage $record) => $record && ! $record->replied_at)
                            ->disabled(function (?WaInboxMessage $record) {
                                $userId = auth()->user()?->id;

                                if (! $record) {
                                    return false;
                                }

                                if ($record->replied_at) {
                                    return true;
                                }

                                if ($record->assigned_to && $record->assigned_to !== $userId) {
                                    return true;
                                }

                                return false;
                            })
                            ->columnSpanFull(),
                        Placeholder::make('assigned_to')
                            ->label('Diambil Oleh')
                            ->content(fn (?WaInboxMessage $record) => $record?->assignedTo?->nama ?? '-'),
                        Placeholder::make('replied_at')
                            ->label('Waktu Balas')
                            ->content(fn (?WaInboxMessage $record) => $record?->replied_at?->format('d/m/Y H:i') ?? '-'),
                        Placeholder::make('status')
                            ->label('Status')
                            ->content(fn (?WaInboxMessage $record) => WaInboxMessage::statusOptions()[$record?->status ?? ''] ?? ($record?->status ?? '-')),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (?string $state) => WaInboxMessage::statusOptions()[$state] ?? $state)
                    ->colors([
                        'warning' => WaInboxMessage::STATUS_NEW,
                        'info' => WaInboxMessage::STATUS_ASSIGNED,
                        'success' => WaInboxMessage::STATUS_REPLIED,
                    ])
                    ->icons([
                        WaInboxMessage::STATUS_NEW => 'heroicon-m-sparkles',
                        WaInboxMessage::STATUS_ASSIGNED => 'heroicon-m-user',
                        WaInboxMessage::STATUS_REPLIED => 'heroicon-m-check-badge',
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('sender_number')
                    ->label('Nomor Pengirim')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sender_name')
                    ->label('Nama Pengirim')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('message')
                    ->label('Pesan')
                    ->limit(60)
                    ->wrap()
                    ->searchable(),
                Tables\Columns\TextColumn::make('assignedTo.nama')
                    ->label('Diambil Oleh')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('received_at')
                    ->label('Masuk')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('replied_at')
                    ->label('Dibalas')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('received_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(WaInboxMessage::statusOptions()),
            ])
            ->actions([
                Action::make('ambil')
                    ->label('Ambil')
                    ->icon('heroicon-m-hand-raised')
                    ->visible(fn (WaInboxMessage $record) => $record->assigned_to === null && ! $record->replied_at)
                    ->action(function (WaInboxMessage $record) {
                        $userId = auth()->user()?->id;
                        if (! $userId) {
                            return;
                        }

                        $updated = WaInboxMessage::query()
                            ->whereKey($record->id)
                            ->whereNull('assigned_to')
                            ->update([
                                'assigned_to' => $userId,
                                'assigned_at' => now(),
                                'status' => WaInboxMessage::STATUS_ASSIGNED,
                            ]);

                        if (! $updated) {
                            $record->refresh();
                        }
                    }),
                EditAction::make()
                    ->label('Balas')
                    ->visible(function (WaInboxMessage $record) {
                        $userId = auth()->user()?->id;

                        return $record->assigned_to === null || $record->assigned_to === $userId;
                    }),
            ]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return RoleAccess::canSeeNav(auth()->user(), 'filament.admin.resources.wa-inbox-messages');
    }

    public static function canEdit(Model $record): bool
    {
        $userId = auth()->user()?->id;
        if (! $userId) {
            return false;
        }

        return $record->assigned_to === null || $record->assigned_to === $userId;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWaInboxMessages::route('/'),
            'edit' => EditWaInboxMessage::route('/{record}/edit'),
        ];
    }

    protected static function signatureForUser(?Personil $user): ?string
    {
        $name = trim((string) ($user?->nama ?? ''));
        if ($name === '') {
            return null;
        }

        $parts = preg_split('/\\s+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) >= 2) {
            $initials = mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
        } else {
            $initials = mb_strtoupper(mb_substr($name, 0, 2));
        }

        return '-' . $initials;
    }
}
