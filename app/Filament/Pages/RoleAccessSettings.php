<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\RoleAccessSetting;
use App\Support\RoleAccess;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
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

        foreach (UserRole::cases() as $role) {
            $state[$role->value] = RoleAccessSetting::allowedPagesFor($role);
        }

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        $options = RoleAccess::pageOptions();

        return $schema
            ->components([
                Section::make('Admin')
                    ->description('Hak akses admin (biasanya semua halaman).')
                    ->schema([
                        CheckboxList::make(UserRole::Admin->value)
                            ->label('Halaman yang diizinkan')
                            ->options($options)
                            ->bulkToggleable()
                            ->columns(2),
                    ]),
                Section::make('Arsiparis')
                    ->description('Atur halaman yang dapat diakses role arsiparis.')
                    ->schema([
                        CheckboxList::make(UserRole::Arsiparis->value)
                            ->label('Halaman yang diizinkan')
                            ->options($options)
                            ->bulkToggleable()
                            ->columns(2),
                    ]),
                Section::make('Pengguna')
                    ->description('Atur halaman yang dapat diakses role pengguna.')
                    ->schema([
                        CheckboxList::make(UserRole::Pengguna->value)
                            ->label('Halaman yang diizinkan')
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

        foreach (UserRole::cases() as $role) {
            $pages = $state[$role->value] ?? [];

            if ($role === UserRole::Admin) {
                if (empty($pages)) {
                    $pages = ['*'];
                }

                if (! in_array('filament.admin.pages.role-access-settings', $pages, true)) {
                    $pages[] = 'filament.admin.pages.role-access-settings';
                }
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
}
