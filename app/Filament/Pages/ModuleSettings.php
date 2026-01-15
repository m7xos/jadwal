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
use Filament\Schemas\Components\Fieldset;
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
        $groups = $this->getModuleOptionGroups();
        $all = $this->flattenGroupOptions($groups);
        $enabled = ModuleSetting::enabledPages();
        $enabled = array_values(array_diff($enabled, ['filament.admin.pages.module-settings']));

        $this->form->fill([
            'enabled_page_groups' => $this->buildGroupedState(empty($enabled) ? $all : $enabled, $groups),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $groups = $this->getModuleOptionGroups();
        $components = [];

        foreach ($groups as $groupKey => $group) {
            $components[] = Fieldset::make($group['label'])
                ->schema([
                    CheckboxList::make("enabled_page_groups.{$groupKey}")
                        ->hiddenLabel()
                        ->options($group['options'])
                        ->bulkToggleable()
                        ->columns(2),
                ]);
        }

        return $schema
            ->components([
                Section::make('Modul Aplikasi')
                    ->description('Pilih modul yang ditampilkan di sidebar untuk semua pengguna.')
                    ->schema($components),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $pages = $this->flattenGroupedSelections($state['enabled_page_groups'] ?? []);
        $pages = array_values(array_diff($pages, ['filament.admin.pages.module-settings']));

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
        return auth()->user()?->isAdmin() === true;
    }

    /**
     * @return array<string, array{label: string, options: array<string, string>}>
     */
    protected function getModuleOptionGroups(): array
    {
        return RoleAccess::pageOptionGroups(false, [
            '*',
            'filament.admin.pages.module-settings',
        ]);
    }

    /**
     * @param  array<string, array{label: string, options: array<string, string>}>  $groups
     * @return array<string, array<int, string>>
     */
    protected function buildGroupedState(array $selected, array $groups): array
    {
        $state = [];

        foreach ($groups as $groupKey => $group) {
            $options = array_keys($group['options']);
            $state[$groupKey] = array_values(array_intersect($selected, $options));
        }

        return $state;
    }

    /**
     * @param  array<string, array{label: string, options: array<string, string>}>  $groups
     * @return array<int, string>
     */
    protected function flattenGroupOptions(array $groups): array
    {
        $options = [];

        foreach ($groups as $group) {
            $options = array_merge($options, array_keys($group['options']));
        }

        return array_values(array_unique($options));
    }

    /**
     * @param  array<string, mixed>  $groupState
     * @return array<int, string>
     */
    protected function flattenGroupedSelections(array $groupState): array
    {
        $pages = [];

        foreach ($groupState as $selected) {
            if (! is_array($selected)) {
                continue;
            }

            $pages = array_merge($pages, $selected);
        }

        return array_values(array_unique(array_filter($pages, 'is_string')));
    }
}
