<?php

namespace App\Filament\Pages;

use App\Models\ModuleSetting;
use App\Support\RoleAccess;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;

class ModuleSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationLabel = 'Pengaturan Modul';
    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';
    protected static ?string $slug = 'module-settings';
    protected static ?int $navigationSort = 210;

    protected string $view = 'filament.pages.module-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $options = RoleAccess::pageOptions(false);
        $all = array_values(array_filter(array_keys($options), fn ($key) => $key !== '*'));
        $enabled = ModuleSetting::enabledPages();

        $this->form->fill([
            'enabled_pages' => empty($enabled) ? $all : $enabled,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $options = RoleAccess::pageOptions(false);
        unset($options['*']);

        return $schema
            ->components([
                Section::make('Modul Aplikasi')
                    ->description('Pilih modul yang ditampilkan di sidebar untuk semua pengguna.')
                    ->schema([
                        CheckboxList::make('enabled_pages')
                            ->label('Modul yang ditampilkan')
                            ->options($options)
                            ->bulkToggleable()
                            ->columns(2),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $pages = array_values(array_unique($state['enabled_pages'] ?? []));

        if (empty($pages)) {
            $pages = ModuleSetting::defaultEnabledPages();
        }

        $setting = ModuleSetting::current();
        $setting->fill(['enabled_pages' => $pages]);
        $setting->save();

        Notification::make()
            ->title('Pengaturan modul disimpan')
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
        return RoleAccess::canSeeNav(auth()->user(), 'filament.admin.pages.module-settings');
    }
}
