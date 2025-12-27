<?php

namespace App\Http\Controllers;

use App\Models\LayananPublik;
use App\Models\LayananPublikRequest;
use App\Services\LayananPublikRequestService;
use Illuminate\Http\Request;

class PublicLayananController extends Controller
{
    public function create()
    {
        $layanan = LayananPublik::query()
            ->where('aktif', true)
            ->orderBy('nama')
            ->get();

        return view('public.layanan.register', [
            'layanan' => $layanan,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama_pemohon' => ['required', 'string', 'max:255'],
            'no_wa_pemohon' => ['required', 'string', 'max:30'],
            'layanan_publik_id' => ['required', 'exists:layanan_publiks,id'],
        ]);

        /** @var LayananPublikRequestService $service */
        $service = app(LayananPublikRequestService::class);

        $register = $service->createRequest([
            'layanan_publik_id' => $data['layanan_publik_id'],
            'nama_pemohon' => $data['nama_pemohon'],
            'no_wa_pemohon' => $data['no_wa_pemohon'],
            'tanggal_masuk' => now(),
            'source' => 'public',
        ]);

        $register->load('layanan');

        return redirect()
            ->route('public.layanan.register')
            ->with('register_success', [
                'kode_register' => $register->kode_register,
                'queue_number' => $register->queue_number,
                'layanan' => $register->layanan?->nama ?? null,
            ]);
    }

    public function show(Request $request, string $kode)
    {
        $register = strtoupper(trim($kode));

        $layanan = LayananPublikRequest::query()
            ->with(['layanan', 'statusLogs' => fn ($query) => $query->orderBy('created_at')])
            ->where('kode_register', $register)
            ->first();

        return view('public.layanan.status', [
            'layanan' => $layanan,
            'kode' => $register,
        ]);
    }

    public function print(Request $request, string $kode)
    {
        $register = strtoupper(trim($kode));

        $layanan = LayananPublikRequest::query()
            ->with('layanan')
            ->where('kode_register', $register)
            ->firstOrFail();

        return view('public.layanan.print', [
            'layanan' => $layanan,
        ]);
    }
}
