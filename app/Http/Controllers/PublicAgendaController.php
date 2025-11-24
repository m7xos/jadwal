<?php

namespace App\Http\Controllers;

use App\Models\Kegiatan;
use Carbon\Carbon;

class PublicAgendaController extends Controller
{
    public function index()
    {
        $today = Carbon::today();

        $upcoming = Kegiatan::with('personils')
            ->whereDate('tanggal', '>=', $today)
            ->orderBy('tanggal')
            ->orderBy('waktu')
            ->get();

        $past = Kegiatan::with('personils')
            ->whereDate('tanggal', '<', $today)
            ->orderByDesc('tanggal')
            ->orderBy('waktu')
            ->limit(20)
            ->get();

        return view('public.agenda.index', [
            'today'    => $today,
            'upcoming' => $upcoming,
            'past'     => $past,
        ]);
    }
	
	public function tv()
    {
        $today = Carbon::today();

        $agendaToday = Kegiatan::with('personils')
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
