<?php

namespace App\Filament\Pages;

use App\Models\VehicleTax;
use App\Support\RoleAccess;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Support\Carbon;
use UnitEnum;

class LaporanPembayaranPajak extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Laporan Pembayaran Pajak';
    protected static string|UnitEnum|null $navigationGroup = 'Laporan';
    protected static ?string $slug = 'laporan-pembayaran-pajak';
    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.pages.laporan-pembayaran-pajak';

    public static function shouldRegisterNavigation(): bool
    {
        return RoleAccess::canSeeNav(auth()->user(), 'filament.admin.pages.laporan-pembayaran-pajak');
    }

    public function table(Table $table): Table
    {
        $now = now()->startOfDay();
        $end = $now->copy()->addYear();

        return $table
            ->query(
                VehicleTax::query()
                    ->with('personil')
                    ->where(function ($query) use ($now, $end) {
                        $query->whereBetween('tgl_pajak_tahunan', [$now, $end])
                            ->orWhereBetween('tgl_pajak_lima_tahunan', [$now, $end]);
                    })
            )
            ->defaultSort('tgl_pajak_tahunan')
            ->columns([
                Tables\Columns\TextColumn::make('plat_nomor')
                    ->label('No. Polisi')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('jenis_kendaraan')
                    ->label('Jenis')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('personil.nama')
                    ->label('Pemegang/Pengguna')
                    ->formatStateUsing(function ($state, VehicleTax $record) {
                        $nama = trim((string) ($record->personil->nama ?? ''));
                        $jabatan = trim((string) ($record->personil->jabatan ?? ''));

                        if ($nama === '' && $jabatan === '') {
                            return '-';
                        }

                        return $jabatan !== '' ? "{$nama} - {$jabatan}" : $nama;
                    })
                    ->searchable(query: function ($query, string $search) {
                        return $query->whereHas('personil', function ($q) use ($search) {
                            $q->where('nama', 'like', "%{$search}%")
                                ->orWhere('jabatan', 'like', "%{$search}%");
                        });
                    }),
                Tables\Columns\TextColumn::make('tgl_pajak_tahunan')
                    ->label('Jatuh Tempo Tahunan')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('tgl_pajak_lima_tahunan')
                    ->label('Jatuh Tempo 5 Tahunan')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('next_due')
                    ->label('Jatuh Tempo Terdekat')
                    ->state(function (VehicleTax $record) {
                        $due = $this->nearestDueDate($record);

                        return $due ? $due->format('d M Y') : '-';
                    })
                    ->sortable(query: function ($query, string $direction) use ($now, $end) {
                        // Sort by tahunan first, then lima tahunan as fallback
                        $query->orderByRaw('COALESCE(tgl_pajak_tahunan, tgl_pajak_lima_tahunan) ' . $direction);
                    }),
                Tables\Columns\BadgeColumn::make('status_pajak')
                    ->label('Status Bayar')
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'lunas' => 'Sudah Lunas',
                            default => 'Belum Lunas',
                        };
                    })
                    ->colors([
                        'success' => 'lunas',
                        'danger' => fn ($state) => $state !== 'lunas',
                    ]),
            ])
            ->filters([]);
    }

    protected function nearestDueDate(VehicleTax $record): ?Carbon
    {
        $candidates = collect([$record->tgl_pajak_tahunan, $record->tgl_pajak_lima_tahunan])
            ->filter();

        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates->sort()->first();
    }
}
