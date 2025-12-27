<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FollowUpReminderResource\Pages;
use App\Models\FollowUpReminder;
use App\Models\Group;
use App\Models\Personil;
use App\Services\FollowUpReminderService;
use App\Support\RoleAccess;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use UnitEnum;

class FollowUpReminderResource extends Resource
{
    protected static ?string $model = FollowUpReminder::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';
    protected static ?string $navigationLabel = 'Pengingat Kegiatan Lainnya';
    protected static ?string $pluralModelLabel = 'Pengingat Kegiatan Lainnya';
    protected static ?string $modelLabel = 'Pengingat Kegiatan Lainnya';
    protected static string|UnitEnum|null $navigationGroup = 'Manajemen Kegiatan';
    protected static ?string $slug = 'follow-up-reminders';
    protected static ?int $navigationSort = 15;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Data Pengingat')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('personil_id')
                            ->label('Nama Personil')
                            ->options(fn () => Personil::query()
                                ->orderBy('nama')
                                ->get()
                                ->mapWithKeys(function (Personil $personil) {
                                    $nama = trim((string) ($personil->nama ?? ''));
                                    $jabatan = trim((string) ($personil->jabatan ?? ''));

                                    if ($jabatan !== '') {
                                        return [$personil->id => "{$nama} - {$jabatan}"];
                                    }

                                    return [$personil->id => $nama];
                                }))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->getSearchResultsUsing(function (string $search): array {
                                $term = trim($search);

                                return Personil::query()
                                    ->when($term !== '', function ($query) use ($term) {
                                        $query->where(function ($q) use ($term) {
                                            $q->where('nama', 'like', "%{$term}%")
                                                ->orWhere('jabatan', 'like', "%{$term}%");
                                        });
                                    })
                                    ->orderBy('nama')
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(function (Personil $personil) {
                                        $nama = trim((string) ($personil->nama ?? ''));
                                        $jabatan = trim((string) ($personil->jabatan ?? ''));

                                        if ($jabatan !== '') {
                                            return [$personil->id => "{$nama} - {$jabatan}"];
                                        }

                                        return [$personil->id => $nama];
                                    })
                                    ->all();
                            })
                            ->afterStateUpdated(function ($state, callable $set) {
                                if (! $state) {
                                    return;
                                }

                                $personil = Personil::find($state);

                                if ($personil && $personil->no_wa) {
                                    $set('no_wa', $personil->no_wa);
                                }
                            }),

                        TextInput::make('no_wa')
                            ->label('Nomor WA Personil')
                            ->placeholder('Contoh: 6281234567890')
                            ->helperText('Nomor tujuan pengingat. Bila kosong di data user, isi manual di sini.')
                            ->required()
                            ->maxLength(30),

                        Select::make('send_via')
                            ->label('Kirim melalui')
                            ->options([
                                'personal' => 'Japri (personal)',
                                'group' => 'Grup WA',
                            ])
                            ->default('personal')
                            ->live()
                            ->required(),

                        Select::make('group_id')
                            ->label('Pilih Grup WA')
                            ->options(fn () => Group::query()
                                ->orderBy('nama')
                                ->pluck('nama', 'id'))
                            ->searchable()
                            ->preload()
                            ->visible(fn (callable $get) => $get('send_via') === 'group')
                            ->required(fn (callable $get) => $get('send_via') === 'group')
                            ->helperText('Wajib jika kirim via grup.'),

                        TextInput::make('nama_kegiatan')
                            ->label('Nama Kegiatan')
                            ->required()
                            ->maxLength(255),

                        DatePicker::make('tanggal')
                            ->label('Tanggal')
                            ->required()
                            ->default(now())
                            ->native(false),

                        TimePicker::make('jam')
                            ->label('Jam')
                            ->seconds(false)
                            ->required()
                            ->default(fn () => now()->format('H:i')),

                        TextInput::make('tempat')
                            ->label('Tempat')
                            ->maxLength(255)
                            ->helperText('Opsional, isi bila ada.'),

                        Textarea::make('keterangan')
                            ->label('Keterangan')
                            ->rows(3)
                            ->placeholder('Catatan tambahan (opsional)')
                            ->maxLength(1000),

                        Placeholder::make('info_pola')
                            ->label('Pola Pengingat')
                            ->content('Pengingat akan dikirim setiap 30 menit sampai penerima membalas pesan dengan kata kunci "terima kasih".'),
                    ])
                    ->columns(2),
            ]);
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
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('personil.nama')
                    ->label('Personil')
                    ->formatStateUsing(function ($state, FollowUpReminder $record) {
                        $nama = trim((string) ($record->personil->nama ?? ''));
                        $jabatan = trim((string) ($record->personil->jabatan ?? ''));

                        if ($nama === '' && $jabatan === '') {
                            return '-';
                        }

                        if ($jabatan !== '') {
                            return "{$nama} - {$jabatan}";
                        }

                        return $nama;
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
                Tables\Columns\TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('jam')
                    ->label('Jam')
                    ->formatStateUsing(function ($state) {
                        if (! $state) {
                            return '-';
                        }

                        try {
                            return Carbon::parse($state)->format('H:i') . ' WIB';
                        } catch (\Throwable) {
                            return $state;
                        }
                    }),
                Tables\Columns\BadgeColumn::make('send_via')
                    ->label('Kirim')
                    ->formatStateUsing(fn ($state) => $state === 'group' ? 'Grup' : 'Japri')
                    ->colors([
                        'warning' => 'group',
                        'primary' => 'personal',
                    ]),
                Tables\Columns\TextColumn::make('group.nama')
                    ->label('Grup WA')
                    ->toggleable()
                    ->limit(20),
                Tables\Columns\TextColumn::make('tempat')
                    ->label('Tempat')
                    ->limit(20)
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->limit(40)
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'acknowledged',
                        'warning' => 'pending',
                    ])
                    ->icons([
                        'acknowledged' => 'heroicon-m-check-badge',
                        'pending' => 'heroicon-m-bell-alert',
                    ]),
                Tables\Columns\TextColumn::make('sent_count')
                    ->label('Dikirim')
                    ->suffix('x')
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_sent_at')
                    ->label('Terakhir Kirim')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Action::make('send_now')
                    ->label('Kirim Sekarang')
                    ->icon('heroicon-m-paper-airplane')
                    ->visible(fn (FollowUpReminder $record) => $record->acknowledged_at === null)
                    ->action(function (FollowUpReminder $record) {
                        /** @var FollowUpReminderService $service */
                        $service = app(FollowUpReminderService::class);
                        $result = $service->send($record);

                        if ($result['success'] ?? false) {
                            Notification::make()
                                ->title('Pengingat dikirim')
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
                Action::make('mark_ack')
                    ->label('Tandai Selesai')
                    ->icon('heroicon-m-check')
                    ->requiresConfirmation()
                    ->visible(fn (FollowUpReminder $record) => $record->acknowledged_at === null)
                    ->action(function (FollowUpReminder $record) {
                        $record->acknowledged_at = now();
                        $record->next_send_at = null;
                        $record->status = 'acknowledged';
                        $record->save();

                        Notification::make()
                            ->title('Pengingat dihentikan')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return RoleAccess::canSeeNav(auth()->user(), 'filament.admin.resources.follow-up-reminders');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFollowUpReminders::route('/'),
            'create' => Pages\CreateFollowUpReminder::route('/create'),
            'edit' => Pages\EditFollowUpReminder::route('/{record}/edit'),
        ];
    }
}
