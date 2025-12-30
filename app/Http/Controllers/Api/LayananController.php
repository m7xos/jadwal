<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LayananPublik;
use App\Models\LayananPublikRequest;
use App\Services\LayananPublikRequestService;
use App\Support\PhoneNumber;
use App\Support\RoleAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LayananController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->ensureAccess($request->user(), 'filament.admin.resources.layanan-publik-register');

        $aktif = $request->input('aktif');
        $query = LayananPublik::query();
        if ($aktif !== null) {
            $query->where('aktif', filter_var($aktif, FILTER_VALIDATE_BOOLEAN));
        }

        $layanan = $query->orderBy('nama')->get();

        return response()->json($layanan->map(fn (LayananPublik $item) => [
            'id' => $item->id,
            'nama' => $item->nama,
            'kategori' => $item->kategori,
            'deskripsi' => $item->deskripsi,
            'aktif' => (bool) $item->aktif,
        ]));
    }

    public function store(Request $request, LayananPublikRequestService $service): JsonResponse
    {
        $this->ensureAccess($request->user(), 'filament.admin.resources.layanan-publik-register');

        $data = $request->validate([
            'nama_pemohon' => ['required', 'string', 'max:255'],
            'no_wa_pemohon' => ['required', 'string', 'max:30'],
            'layanan_publik_id' => ['required', 'exists:layanan_publiks,id'],
        ]);

        $normalized = PhoneNumber::normalize($data['no_wa_pemohon']);
        $data['no_wa_pemohon'] = $normalized ?? $data['no_wa_pemohon'];

        $register = $service->createRequest([
            'layanan_publik_id' => $data['layanan_publik_id'],
            'nama_pemohon' => $data['nama_pemohon'],
            'no_wa_pemohon' => $data['no_wa_pemohon'],
            'tanggal_masuk' => now(),
            'source' => 'mobile',
        ], $request->user()?->id);

        $register->load('layanan', 'statusLogs');

        return response()->json($this->formatRequest($register), 201);
    }

    public function status(string $kode): JsonResponse
    {
        $register = strtoupper(trim($kode));

        $layanan = LayananPublikRequest::query()
            ->with(['layanan', 'statusLogs' => fn ($query) => $query->orderBy('created_at')])
            ->where('kode_register', $register)
            ->first();

        if (! $layanan) {
            return response()->json(['message' => 'Data tidak ditemukan.'], 404);
        }

        return response()->json($this->formatRequest($layanan));
    }

    protected function ensureAccess($personil, string $identifier): void
    {
        if (! $personil || ! RoleAccess::canSeeNav($personil, $identifier)) {
            abort(403, 'Tidak diizinkan.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatRequest(LayananPublikRequest $request): array
    {
        return [
            'id' => $request->id,
            'kode_register' => $request->kode_register,
            'queue_number' => $request->queue_number,
            'nama_pemohon' => $request->nama_pemohon,
            'no_wa_pemohon' => $request->no_wa_pemohon,
            'status' => $request->status,
            'status_label' => $request->status_label,
            'tanggal_masuk' => $request->tanggal_masuk?->format('Y-m-d'),
            'tanggal_selesai' => $request->tanggal_selesai?->format('Y-m-d'),
            'perangkat_desa_nama' => $request->perangkat_desa_nama,
            'perangkat_desa_wa' => $request->perangkat_desa_wa,
            'catatan' => $request->catatan,
            'layanan' => $request->layanan ? [
                'id' => $request->layanan->id,
                'nama' => $request->layanan->nama,
                'kategori' => $request->layanan->kategori,
            ] : null,
            'status_logs' => $request->statusLogs->map(fn ($log) => [
                'id' => $log->id,
                'status' => $log->status,
                'catatan' => $log->catatan,
                'created_at' => $log->created_at?->toISOString(),
            ])->values(),
        ];
    }
}
