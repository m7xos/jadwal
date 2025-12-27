<?php

namespace App\Http\Controllers;

use App\Models\LayananPublikRequest;
use Illuminate\Http\Request;

class PublicLayananController extends Controller
{
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
