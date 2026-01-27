<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\RoleAccessSetting;
use App\Support\RoleAccess;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use UnitEnum;
use Filament\Schemas\Schema;

class RoleAccessSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-lock-closed';
    protected static ?string $navigationLabel = 'Pengaturan Akses';
    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';
    protected static ?string $slug = 'role-access-settings';
    protected static ?int $navigationSort = 200;

    protected string $view = 'filament.pages.role-access-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $state = [];
        $groups = $this->getGroupedOptions();

        foreach (UserRole::cases() as $role) {
            $allowed = RoleAccessSetting::allowedPagesFor($role);
            $state[$role->value] = [
                'groups' => $this->buildGroupedRoleState($allowed, $groups),
            ];
        }

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        $groups = $this->getGroupedOptions();

        return $schema
            ->components([
                Section::make('Admin')
                    ->description('Hak akses admin (biasanya semua halaman).')
                    ->schema($this->buildRoleGroupComponents(UserRole::Admin, $groups)),
                Section::make('Arsiparis')
                    ->description('Atur halaman yang dapat diakses role arsiparis.')
                    ->schema($this->buildRoleGroupComponents(UserRole::Arsiparis, $groups)),
                Section::make('Pengguna')
                    ->description('Atur halaman yang dapat diakses role pengguna.')
                    ->schema($this->buildRoleGroupComponents(UserRole::Pengguna, $groups)),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $groups = $this->getGroupedOptions();
        $knownOptions = $this->flattenGroupOptions($groups);

        foreach (UserRole::cases() as $role) {
            $roleState = $state[$role->value] ?? [];
            $groupState = $roleState['groups'] ?? [];
            $pages = $this->flattenGroupedSelections($groupState);
            $hiddenPages = array_values(array_diff(RoleAccessSetting::allowedPagesFor($role), $knownOptions));
            $pages = array_values(array_unique(array_merge($pages, $hiddenPages)));

            if ($role === UserRole::Admin) {
                if (empty($pages)) {
                    $pages = ['*'];
                }

                if (! in_array('filament.admin.pages.role-access-settings', $pages, true)) {
                    $pages[] = 'filament.admin.pages.role-access-settings';
                }
                if (! in_array('filament.admin.pages.module-settings', $pages, true)) {
                    $pages[] = 'filament.admin.pages.module-settings';
                }
            } else {
                $pages = array_values(array_diff($pages, [
                    'filament.admin.pages.module-settings',
                ]));
            }

            $pages = array_values(array_unique($pages));

            RoleAccessSetting::updateOrCreate(
                ['role' => $role->value],
                ['allowed_pages' => $pages],
            );
        }

        Notification::make()
            ->title('Pengaturan akses disimpan')
            ->success()
            ->send();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->isAdmin() === true;
    }

    /**
     * @return array<string, array{label: string, options: array<string, string>}>
     */
    protected function getGroupedOptions(): array
    {
        return RoleAccess::pageOptionGroups();
    }

    /**
     * @param  array<string, array{label: string, options: array<string, string>}>  $groups
     * @return array<int, Fieldset>
     */
    protected function buildRoleGroupComponents(UserRole $role, array $groups): array
    {
        $components = [];

        foreach ($groups as $groupKey => $group) {
            $components[] = Fieldset::make($group['label'])
                ->schema([
                    CheckboxList::make($role->value . '.groups.' . $groupKey)
                        ->hiddenLabel()
                        ->options($group['options'])
                        ->bulkToggleable()
                        ->columns(2),
                ]);
        }

        return $components;
    }

    /**
     * @param  array<int, string>  $allowed
     * @param  array<string, array{label: string, options: array<string, string>}>  $groups
     * @return array<string, array<int, string>>
     */
    protected function buildGroupedRoleState(array $allowed, array $groups): array
    {
        $grouped = [];

        foreach ($groups as $groupKey => $group) {
            $options = array_keys($group['options']);
            $grouped[$groupKey] = array_values(array_intersect($allowed, $options));
        }

        return $grouped;
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
