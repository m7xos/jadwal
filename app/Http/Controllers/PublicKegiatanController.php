<?php

namespace App\Http\Controllers;

use App\Models\Kegiatan;

class PublicKegiatanController extends Controller
{
    public function index()
    {
        $today = now()->toDateString(); // YYYY-MM-DD

        // Agenda HARI INI
        $todayAgenda = Kegiatan::with('personils')
            ->whereDate('tanggal', $today)
            ->orderBy('waktu')              // optional, kalau format jam rapi
            ->get();

        // Agenda HARI BERIKUTNYA (mulai besok)
        $nextAgenda = Kegiatan::with('personils')
            ->whereDate('tanggal', '>', $today)
            ->orderBy('tanggal')
            ->orderBy('waktu')
            ->get();

        // Agenda yang sudah lewat (20 terakhir)
        $past = Kegiatan::with('personils')
            ->whereDate('tanggal', '<', $today)
            ->orderByDesc('tanggal')
            ->limit(20)
            ->get();

        return view('public.agenda.index', [
            'todayAgenda' => $todayAgenda,
            'nextAgenda'  => $nextAgenda,
            'past'        => $past,
        ]);
    }
	
	public function tv()
	{
		$today = now()->toDateString();

		$agendaToday = Kegiatan::with('personils')
			->whereDate('tanggal', $today)
			->orderBy('waktu')
			->orderBy('nama_kegiatan')
			->get();

		return view('public.agenda.tv', [
			'agendaToday' => $agendaToday,
		]);
	}

}


