<?php

namespace App\Http\Controllers;

use App\Models\Kegiatan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Crypt;

class KegiatanSuratController extends Controller
{
    /**
     * Tampilkan halaman viewer PDF untuk surat undangan.
     *
     * Contoh URL: /u/123
     */
    public function show(Kegiatan $kegiatan)
    {
        if (! $kegiatan->surat_undangan) {
            abort(404, 'Surat undangan tidak ditemukan.');
        }

        $fileUrl = $kegiatan->surat_preview_url;

        return view('kegiatan.surat-view', [
            'kegiatan' => $kegiatan,
            'fileUrl'  => $fileUrl,
        ]);
    }

    public function preview(string $token)
    {
        try {
            $path = Crypt::decryptString($token);
        } catch (\Throwable $th) {
            abort(404);
        }

        if (! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        $fullPath = Storage::disk('public')->path($path);
        $filename = basename($path);

        return response()->file($fullPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }
}
