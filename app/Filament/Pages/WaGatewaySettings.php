<?php

namespace App\Filament\Pages;

use App\Models\WaGatewaySetting;
use App\Support\PhoneNumber;
use App\Support\RoleAccess;
use BackedEnum;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;
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
            'registry_path' => $setting->registry_path,
            'registry_url' => $setting->registry_url,
            'session_id' => $setting->session_id,
            'registry_token' => $setting->registry_token,
            'registry_user' => $setting->registry_user,
            'registry_pass' => $setting->registry_pass,
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
                            ->label('Master Key (opsional)')
                            ->helperText('Jika wa-gateway mengaktifkan master key, isi di sini.')
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

                Section::make('Registry Token (opsional)')
                    ->description('Dipakai jika ingin sync token dari device registry wa-gateway.')
                    ->schema([
                        TextInput::make('registry_path')
                            ->label('Registry Path (local)')
                            ->placeholder('/home/wa-gateway/wa_credentials/device-registry.json')
                            ->maxLength(255),
                        TextInput::make('registry_url')
                            ->label('Registry URL (remote)')
                            ->placeholder('https://gateway.example.com/device-registry.json')
                            ->maxLength(255),
                        TextInput::make('session_id')
                            ->label('Nomor Sender / Session ID (opsional)')
                            ->helperText('Masukkan nomor (08xxx akan otomatis menjadi 62xxx) atau sessionId dari device-registry.json.')
                            ->afterStateUpdated(function ($state, callable $set) {
                                $raw = trim((string) ($state ?? ''));
                                if ($raw === '' || ! preg_match('/^[0-9+]+$/', $raw)) {
                                    return;
                                }

                                $normalized = PhoneNumber::normalize($raw);
                                if ($normalized && $normalized !== $raw) {
                                    $set('session_id', $normalized);
                                }
                            })
                            ->maxLength(255),
                        TextInput::make('registry_token')
                            ->label('Registry Token (Bearer)')
                            ->maxLength(255),
                        TextInput::make('registry_user')
                            ->label('Registry User')
                            ->maxLength(255),
                        TextInput::make('registry_pass')
                            ->label('Registry Password')
                            ->maxLength(255),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $setting = WaGatewaySetting::current();

        $state['session_id'] = $this->normalizeSessionId($state['session_id'] ?? null);

        $setting->fill($state);
        $setting->save();

        Notification::make()
            ->title('Pengaturan WA Gateway disimpan')
            ->success()
            ->send();
    }

    public function syncToken(): void
    {
        $state = $this->form->getState();
        $sessionId = $this->normalizeSessionId($state['session_id'] ?? null);

        $exitCode = Artisan::call('wa-gateway:sync-token', array_filter([
            '--session' => $sessionId,
        ], fn ($value) => $value !== null && $value !== ''));

        $output = trim(Artisan::output());

        if ($exitCode === 0) {
            $setting = WaGatewaySetting::current()->refresh();
            $this->form->fill([
                'token' => $setting->token,
                'key' => $setting->key,
                'secret_key' => $setting->secret_key,
                'session_id' => $setting->session_id,
            ]);

            Notification::make()
                ->title('Token berhasil disinkronkan')
                ->body($output ?: null)
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Gagal sinkron token')
                ->body($output ?: 'Periksa registry path/URL dan session ID.')
                ->danger()
                ->send();
        }
    }

    public function testConnection(): void
    {
        $state = $this->form->getState();

        $baseUrl = rtrim(trim((string) ($state['base_url'] ?? '')), '/');
        $token = trim((string) ($state['token'] ?? ''));
        $secret = trim((string) ($state['secret_key'] ?? ''));
        $masterKey = trim((string) ($state['key'] ?? ''));

        if ($baseUrl === '' || $token === '') {
            Notification::make()
                ->title('Data koneksi belum lengkap')
                ->body('Isi Base URL dan Token Device sebelum tes koneksi.')
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
                Notification::make()
                    ->title('Tes koneksi gagal')
                    ->body('HTTP ' . $response->status())
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

    protected function normalizeSessionId(?string $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^[0-9+]+$/', $raw)) {
            return PhoneNumber::normalize($raw) ?? $raw;
        }

        return $raw;
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
