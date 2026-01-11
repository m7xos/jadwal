<?php

namespace App\Http\Controllers;

use App\Models\Personil;
use App\Services\HolidayCalendarService;
use Carbon\Carbon;

class PublicPejabatStatusController extends Controller
{
    public function index()
    {
        $today = Carbon::today();
        $now = now();
        $holidayService = app(HolidayCalendarService::class);
        $isHoliday = $holidayService->isHoliday($today);
        $dayOfWeek = (int) $today->dayOfWeekIso;
        $isWorkDay = $dayOfWeek >= 1 && $dayOfWeek <= 5 && ! $isHoliday;
        $isWorkTime = false;

        if ($isWorkDay) {
            $workStart = $today->copy()->setTime(7, 30);
            $workEnd = $dayOfWeek === 5
                ? $today->copy()->setTime(11, 0)
                : $today->copy()->setTime(16, 0);
            $isWorkTime = $now->betweenIncluded($workStart, $workEnd);
        }

        $jabatanTargets = [
            'Camat Watumalang',
            'Sekretaris Kecamatan',
            'Kasi Kesrasos',
            'Kasi Pemerintahan',
            'Kasi Ekbang',
            'Kasi Trantibum Linmas',
            'Kasubag PATEN',
        ];

        $allowedPlaces = [
            'aula kantor kecamatan lantai 2',
            'aula kantor kecamatan',
            'aula lantai 2 kantor kecamatan watumalang',
        ];

        $normalizeNip = function (?string $value): ?string {
            $digits = preg_replace('/\D+/', '', (string) $value);

            return $digits !== '' ? $digits : null;
        };

        $personils = Personil::query()
            ->where(function ($query) use ($jabatanTargets) {
                foreach ($jabatanTargets as $jabatan) {
                    $query->orWhere('jabatan', 'like', '%' . $jabatan . '%');
                }
            })
            ->with(['kegiatans' => function ($query) use ($today) {
                $query->whereDate('tanggal', $today)
                    ->orderBy('waktu')
                    ->orderBy('nama_kegiatan');
            }])
            ->get();

        $normalizePlace = function (?string $value): string {
            $normalized = trim(strtolower((string) $value));

            return preg_replace('/\s+/', ' ', $normalized) ?? '';
        };

        $statuses = collect($jabatanTargets)->map(function (string $jabatan) use ($personils, $allowedPlaces, $normalizePlace, $normalizeNip, $isWorkTime) {
            $personil = $personils->first(function ($item) use ($jabatan) {
                return $item->jabatan && stripos($item->jabatan, $jabatan) !== false;
            });

            if (! $personil) {
                return [
                    'jabatan' => $jabatan,
                    'nama' => 'Belum terdaftar',
                    'nip' => null,
                    'photo_url' => null,
                    'status' => 'Tidak diketahui',
                    'kegiatan' => collect(),
                    'kegiatan_luar' => collect(),
                ];
            }

            $kegiatan = $personil->kegiatans ?? collect();
            $nip = $normalizeNip($personil->nip ?? null);

            $kegiatanLuar = $kegiatan->filter(function ($item) use ($allowedPlaces, $normalizePlace) {
                $tempat = $normalizePlace($item->tempat ?? '');

                return $tempat !== '' && ! in_array($tempat, $allowedPlaces, true);
            })->values();

            if ($kegiatanLuar->isNotEmpty()) {
                $status = 'Dinas Luar';
            } elseif (! $isWorkTime) {
                $status = 'Tidak di Kantor';
            } else {
                $status = 'Di Kantor';
            }
            $photoCandidates = [];
            if (! empty($personil->photo_url)) {
                $photoCandidates[] = $personil->photo_url;
            }
            if ($nip) {
                $baseUrl = 'https://simpeg.wonosobokab.go.id/packages/upload/photo/pegawai/';
                $photoCandidates[] = $baseUrl . $nip . '.jpg';
                $photoCandidates[] = $baseUrl . $nip . '.jpeg';
            }

            return [
                'jabatan' => $jabatan,
                'nama' => $personil->nama ?? '-',
                'nip' => $nip,
                'photo_candidates' => $photoCandidates,
                'status' => $status,
                'kegiatan' => $kegiatan,
                'kegiatan_luar' => $kegiatanLuar,
            ];
        });

        return view('public.pejabat-status.index', [
            'today' => $today,
            'statuses' => $statuses,
        ]);
    }
}
