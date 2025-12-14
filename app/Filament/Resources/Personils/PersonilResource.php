<?php

namespace App\Filament\Resources\Personils;

use App\Filament\Resources\Personils\Pages\CreatePersonil;
use App\Filament\Resources\Personils\Pages\EditPersonil;
use App\Filament\Resources\Personils\Pages\ListPersonils;
use App\Filament\Resources\Personils\Schemas\PersonilForm;
use App\Filament\Resources\Personils\Tables\PersonilsTable;
use App\Imports\PersonilsImport;
use App\Models\Personil;
use App\Support\RoleAccess;
use BackedEnum;
use Filament\Actions\Action;                 // v4: Action dari sini
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use UnitEnum;

class PersonilResource extends Resource
{
    protected static ?string $model = Personil::class;

    // Sesuaikan type dengan Filament v4:
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Personil';
    protected static ?string $pluralModelLabel = 'Personil';
    protected static ?string $modelLabel = 'Personil';

    protected static string|UnitEnum|null $navigationGroup = 'Manajemen Kegiatan';

    public static function form(Schema $schema): Schema
    {
        return PersonilForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        // Konfigurasi tabel dasar tetap pakai class terpisah
        $table = PersonilsTable::configure($table);

        // Tambahkan header action "Import dari Excel"
        return $table->headerActions([
            Action::make('importPersonil')
                ->label('Import dari Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->form([
                    FileUpload::make('file')
                        ->label('File Excel')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
                            'application/vnd.ms-excel', // .xls
                        ])
                        ->directory('import/personil') // storage/app/public/import/personil
                        ->disk('public')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    // $data['file'] = path relatif di disk 'public', misal: "import/personil/xxx.xlsx"
                    $path = Storage::disk('public')->path($data['file']);

                    if (! is_file($path)) {
                        Notification::make()
                            ->title('File tidak ditemukan')
                            ->body('Pastikan Anda mengunggah file Excel yang valid.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $import = new PersonilsImport();

                    Excel::import($import, $path);

                    $count = $import->getRowCount();

                    // Hapus file upload setelah diproses supaya tidak menumpuk di storage
                    Storage::disk('public')->delete($data['file']);

                    Notification::make()
                        ->title('Import personil berhasil')
                        ->body("Total baris yang diimport: {$count}")
                        ->success()
                        ->send();
                })
                ->modalHeading('Import Personil dari Excel')
                ->modalSubmitActionLabel('Import')   // ⬅️ ini yang benar di Filament v4
                ->modalWidth('md'),
        ]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return RoleAccess::canSeeNav(auth()->user(), 'filament.admin.resources.personils');
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListPersonils::route('/'),
            'create' => CreatePersonil::route('/create'),
            'edit'   => EditPersonil::route('/{record}/edit'),
        ];
    }
}
