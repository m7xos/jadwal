<?php

namespace App\Filament\Pages;

use App\Models\VehicleTaxSetting;
use App\Models\Personil;
use App\Support\RoleAccess;
use BackedEnum;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Section as FormSection;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;

class VehicleTaxSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $title = 'Pengaturan Pengurus Barang';
    protected static ?string $navigationLabel = 'Pengurus Barang';
    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';
    protected static ?string $slug = 'pengurus-barang';
    protected static ?int $navigationSort = 25;

    protected string $view = 'filament.pages.vehicle-tax-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $setting = VehicleTaxSetting::current();

        $this->form->fill([
            'personil_id' => $setting->personil_id,
            'pengurus_barang_nama' => $setting->pengurus_barang_nama,
            'pengurus_barang_no_wa' => $setting->pengurus_barang_no_wa,
            'pengurus_barang_nip' => $setting->pengurus_barang_nip,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Data Pengurus Barang')
                    ->description('Isi sekali dan perbarui saat ada pergantian pengurus barang.')
                    ->schema([
                        Select::make('personil_id')
                            ->label('Pilih Pengurus Barang (Personil)')
                            ->options(fn () => Personil::query()
                                ->orderBy('nama')
                                ->pluck('nama', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if (! $state) {
                                    return;
                                }

                                $personil = Personil::find($state);

                                if (! $personil) {
                                    return;
                                }

                                $set('pengurus_barang_nama', $personil->nama);
                                $set('pengurus_barang_no_wa', $personil->no_wa);
                                $set('pengurus_barang_nip', $personil->nip);
                            }),

                        TextInput::make('pengurus_barang_nama')
                            ->label('Nama Pengurus Barang')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Diisi otomatis sesuai personil yang dipilih.'),

                        TextInput::make('pengurus_barang_no_wa')
                            ->label('Nomor WA Pengurus Barang')
                            ->placeholder('Contoh: 6281234567890')
                            ->required()
                            ->maxLength(30)
                            ->helperText('Diisi otomatis sesuai personil yang dipilih.'),

                        TextInput::make('pengurus_barang_nip')
                            ->label('NIP Pengurus Barang')
                            ->maxLength(50)
                            ->helperText('Diisi otomatis sesuai personil yang dipilih.'),

                        Placeholder::make('info_nomor_wa')
                            ->label('Catatan')
                            ->content('Nomor WA akan ikut pesan pengingat pajak kendaraan.'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        if (! empty($state['personil_id'])) {
            $personil = Personil::find($state['personil_id']);

            if ($personil) {
                $state['pengurus_barang_nama'] = $personil->nama;
                $state['pengurus_barang_no_wa'] = $personil->no_wa;
            }
        }

        $setting = VehicleTaxSetting::current();
        $setting->fill($state);
        $setting->save();

        Notification::make()
            ->title('Data pengurus barang disimpan')
            ->success()
            ->send();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->isAdmin() === true;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return RoleAccess::canSeeNav(auth()->user(), 'filament.admin.pages.pengurus-barang');
    }
}
