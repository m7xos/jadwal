<?php

namespace App\Filament\Pages;

use App\Models\WaGatewaySetting;
use App\Support\RoleAccess;
use BackedEnum;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;

class WaGatewaySettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-link';
    protected static ?string $title = 'Pengaturan WA Gateway';
    protected static ?string $navigationLabel = 'WA Gateway';
    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';
    protected static ?string $slug = 'wa-gateway';
    protected static ?int $navigationSort = 26;

    protected string $view = 'filament.pages.wa-gateway-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $setting = WaGatewaySetting::current();

        $this->form->fill([
            'base_url' => $setting->base_url,
            'token' => $setting->token,
            'key' => $setting->key,
            'secret_key' => $setting->secret_key,
            'provider' => $setting->provider ?? 'wa-gateway',
            'finish_whitelist' => $setting->finish_whitelist,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Koneksi WA Gateway')
                    ->description('Data koneksi untuk pengiriman WhatsApp melalui wa-gateway.')
                    ->schema([
                        TextInput::make('base_url')
                            ->label('Base URL')
                            ->placeholder('https://gateway.example.com')
                            ->helperText('Contoh: https://gateway.example.com (tanpa slash di akhir).')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('token')
                            ->label('Token Device')
                            ->helperText('Token device dari wa-gateway (hasil create device).')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('key')
                            ->label('Master Key')
                            ->helperText('Wajib diisi sesuai konfigurasi wa-gateway.')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('secret_key')
                            ->label('Secret Key (opsional)')
                            ->helperText('Tidak dipakai di wa-gateway, disediakan untuk kompatibilitas.')
                            ->maxLength(255),

                        Select::make('provider')
                            ->label('Provider')
                            ->options([
                                'wa-gateway' => 'wa-gateway',
                                'legacy' => 'legacy',
                            ])
                            ->helperText('Gunakan wa-gateway jika memakai JID grup (@g.us).')
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Whitelist Penyelesaian TL')
                    ->description('Nomor tambahan yang boleh menyelesaikan TL (pisahkan dengan koma).')
                    ->schema([
                        Textarea::make('finish_whitelist')
                            ->label('Nomor WA')
                            ->rows(3)
                            ->placeholder('Contoh: 6281234567890,6289876543210'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $setting = WaGatewaySetting::current();

        $setting->fill($state);
        $setting->save();

        Notification::make()
            ->title('Pengaturan WA Gateway disimpan')
            ->success()
            ->send();
    }

    public function testConnection(): void
    {
        $state = $this->form->getState();

        $baseUrl = rtrim(trim((string) ($state['base_url'] ?? '')), '/');
        $token = trim((string) ($state['token'] ?? ''));
        $secret = trim((string) ($state['secret_key'] ?? ''));
        $masterKey = trim((string) ($state['key'] ?? ''));

        if ($baseUrl === '' || $token === '' || $masterKey === '') {
            Notification::make()
                ->title('Data koneksi belum lengkap')
                ->body('Isi Base URL, Token Device, dan Master Key sebelum tes koneksi.')
                ->danger()
                ->send();
            return;
        }

        $authHeader = $secret !== '' ? $token . '.' . $secret : $token;
        $headers = ['Authorization' => $authHeader];
        if ($masterKey !== '') {
            $headers['key'] = $masterKey;
        }

        try {
            $response = Http::withHeaders($headers)
                ->withOptions(['verify' => false])
                ->timeout(8)
                ->get($baseUrl . '/api/device/info');

            if (! $response->successful()) {
                $body = trim((string) $response->body());
                $detail = 'HTTP ' . $response->status() . ' · URL: ' . $baseUrl . '/api/device/info';
                if ($body !== '') {
                    $detail .= ' · Response: ' . Str::limit($body, 300);
                }

                Notification::make()
                    ->title('Tes koneksi gagal')
                    ->body($detail)
                    ->danger()
                    ->send();
                return;
            }

            $status = data_get($response->json(), 'data.0.status') ?? 'unknown';
            $label = is_string($status) ? strtolower($status) : 'unknown';

            Notification::make()
                ->title('Koneksi berhasil')
                ->body('Status device: ' . $label)
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Tes koneksi gagal')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function restartQueueWorkers(): void
    {
        try {
            Artisan::call('queue:restart');

            Notification::make()
                ->title('Worker/queue direstart')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gagal restart worker/queue')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->isAdmin() === true;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return RoleAccess::canSeeNav(auth()->user(), 'filament.admin.pages.wa-gateway');
    }
}
