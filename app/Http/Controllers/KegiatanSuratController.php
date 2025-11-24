<?php

namespace App\Http\Controllers;

use App\Models\Kegiatan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

        // URL publik file PDF dari disk "public"
        $fileUrl = Storage::disk('public')->url($kegiatan->surat_undangan);

        return view('kegiatan.surat-view', [
            'kegiatan' => $kegiatan,
            'fileUrl'  => $fileUrl,
        ]);
    }
}
