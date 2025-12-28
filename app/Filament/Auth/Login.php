<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

class Login extends BaseLogin
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('nip')
            ->label('NIP')
            ->required()
            ->autocomplete('username')
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('Password')
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->autocomplete('current-password')
            ->required()
            ->extraInputAttributes(['tabindex' => 2]);
    }

    protected function getRememberFormComponent(): Component
    {
        return Checkbox::make('remember')
            ->label(__('filament-panels::auth/pages/login.form.remember.label'));
    }

    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        $password = $this->normalizePhone($data['password'] ?? '');

        return [
            'nip' => $data['nip'],
            'password' => $password,
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.nip' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }

    protected function normalizePhone(?string $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '0')) {
            return '62' . substr($digits, 1);
        }

        if (str_starts_with($digits, '62')) {
            return $digits;
        }

        // Jika user mengetik langsung tanpa nol/62, treat as nomor lokal yang diawali 8xxx.
        if (str_starts_with($digits, '8')) {
            return '62' . $digits;
        }

        return $digits;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ]);
    }
}
