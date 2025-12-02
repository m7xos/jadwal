<?php

namespace App\Http\Controllers;

use App\Models\Kegiatan;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PublicAgendaController extends Controller
{
    public function index(Request $request)
	{
		$today = Carbon::today();

		$startInput = $request->input('tanggal_mulai');
		$endInput   = $request->input('tanggal_selesai');

		$startDate = null;
		$endDate   = null;

		// Parse tanggal mulai
		if ($startInput) {
			try {
				$startDate = Carbon::parse($startInput)->startOfDay();
			} catch (\Exception $e) {
				$startDate = null;
			}
		}

		// Parse tanggal selesai
		if ($endInput) {
			try {
				$endDate = Carbon::parse($endInput)->endOfDay();
			} catch (\Exception $e) {
				$endDate = null;
			}
		}

		// Jika dua-duanya ada dan start > end, tukar
		if ($startDate && $endDate && $startDate->greaterThan($endDate)) {
			[$startDate, $endDate] = [$endDate, $startDate];
		}

		// Query agenda HARI INI & MENDATANG (dalam rentang)
                $upcomingQuery = Kegiatan::with('personils')
                        ->where(function ($query) {
                                $query->whereNull('jenis_surat')
                                    ->orWhere('jenis_surat', 'undangan');
                        });

		if ($startDate) {
			$upcomingQuery->whereDate('tanggal', '>=', $startDate);
		} else {
			// default: dari hari ini ke depan
			$upcomingQuery->whereDate('tanggal', '>=', $today);
		}

		if ($endDate) {
			$upcomingQuery->whereDate('tanggal', '<=', $endDate);
		}

		$upcoming = $upcomingQuery
			->orderBy('tanggal')
			->orderBy('waktu')
			->get();

		// Riwayat: sebelum tanggal dasar (pakai tanggal_mulai kalau ada, kalau tidak pakai today)
		$pastBaseDate = $startDate ?? $today;

                $past = Kegiatan::with('personils')
                        ->where(function ($query) {
                                $query->whereNull('jenis_surat')
                                    ->orWhere('jenis_surat', 'undangan');
                        })
                        ->whereDate('tanggal', '<', $pastBaseDate)
			->orderByDesc('tanggal')
			->orderBy('waktu')
			->limit(20)
			->get();

		return view('public.agenda.index', [
			'today'     => $today,     // tetap untuk header "Hari ini"
			'upcoming'  => $upcoming,
			'past'      => $past,
			'startDate' => $startDate,
			'endDate'   => $endDate,
		]);
	}


    public function tv()
    {
        $today = Carbon::today();

        $agendaToday = Kegiatan::with('personils')
            ->where(function ($query) {
                $query->whereNull('jenis_surat')
                    ->orWhere('jenis_surat', 'undangan');
            })
            ->whereDate('tanggal', $today)
            ->orderBy('waktu')
            ->orderBy('nama_kegiatan')
            ->get();

        return view('public.agenda.tv', [
            'agendaToday' => $agendaToday,
            'today'       => $today,
        ]);
    }
}
