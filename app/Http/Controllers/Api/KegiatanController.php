<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kegiatan;
use App\Models\Personil;
use App\Support\RoleAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class KegiatanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->ensureAccess($request->user(), 'filament.admin.resources.kegiatans');

        $query = Kegiatan::query()->with('personils');

        $jenisSurat = $request->input('jenis_surat');
        if (is_string($jenisSurat) && $jenisSurat !== '') {
            $query->where('jenis_surat', $jenisSurat);
        }

        $belumDisposisi = $request->input('belum_disposisi');
        if ($belumDisposisi !== null) {
            $query->where('sudah_disposisi', ! filter_var($belumDisposisi, FILTER_VALIDATE_BOOLEAN));
        }

        $start = $this->parseDate($request->input('tanggal_mulai'));
        if ($start) {
            $query->whereDate('tanggal', '>=', $start);
        }

        $end = $this->parseDate($request->input('tanggal_selesai'));
        if ($end) {
            $query->whereDate('tanggal', '<=', $end);
        }

        $perPage = (int) $request->input('per_page', 20);
        $perPage = $perPage > 0 && $perPage <= 100 ? $perPage : 20;

        $kegiatans = $query
            ->orderByDesc('tanggal')
            ->orderByDesc('waktu')
            ->paginate($perPage);

        $kegiatans->getCollection()->transform(fn (Kegiatan $kegiatan) => $this->formatKegiatan($kegiatan));

        return response()->json($kegiatans);
    }

    public function show(Request $request, Kegiatan $kegiatan): JsonResponse
    {
        $this->ensureAccess($request->user(), 'filament.admin.resources.kegiatans');

        $kegiatan->load('personils');

        return response()->json($this->formatKegiatan($kegiatan));
    }

    public function updateDisposisi(Request $request, Kegiatan $kegiatan): JsonResponse
    {
        $this->ensureAccess($request->user(), 'filament.admin.resources.kegiatans');
        $this->ensureCamatOrSekcam($request->user());

        $data = $request->validate([
            'sudah_disposisi' => ['nullable', 'boolean'],
            'personil_ids' => ['nullable', 'array'],
            'personil_ids.*' => ['integer', 'exists:personils,id'],
        ]);

        $hasPersonils = array_key_exists('personil_ids', $data);
        if ($hasPersonils) {
            $kegiatan->personils()->sync($data['personil_ids'] ?? []);
        }

        if (array_key_exists('sudah_disposisi', $data)) {
            $kegiatan->sudah_disposisi = (bool) $data['sudah_disposisi'];
        } elseif ($hasPersonils) {
            $kegiatan->sudah_disposisi = ! empty($data['personil_ids']);
        }

        if ($kegiatan->isDirty()) {
            $kegiatan->save();
        }

        $kegiatan->load('personils');

        return response()->json($this->formatKegiatan($kegiatan));
    }

    protected function ensureAccess(?Personil $personil, string $identifier): void
    {
        if (! $personil || ! RoleAccess::canSeeNav($personil, $identifier)) {
            abort(403, 'Tidak diizinkan.');
        }
    }

    protected function ensureCamatOrSekcam(?Personil $personil): void
    {
        if (! $personil) {
            abort(403, 'Tidak diizinkan.');
        }

        $jabatan = strtolower(trim((string) $personil->jabatan));
        if ($jabatan === '') {
            abort(403, 'Tidak diizinkan.');
        }

        $camatPatterns = (array) config('jadwal_notifications.camat_jabatan_like', []);
        $sekcamPatterns = (array) config('jadwal_notifications.sekcam_jabatan_like', []);

        $allowed = $this->jabatanMatches($jabatan, $camatPatterns)
            || $this->jabatanMatches($jabatan, $sekcamPatterns);

        if (! $allowed) {
            abort(403, 'Tidak diizinkan.');
        }
    }

    protected function jabatanMatches(string $jabatan, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            $pattern = strtolower(trim((string) $pattern));
            if ($pattern !== '' && str_contains($jabatan, $pattern)) {
                return true;
            }
        }

        return false;
    }

    protected function parseDate(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatKegiatan(Kegiatan $kegiatan): array
    {
        $lampiranUrl = $this->storageUrl($kegiatan->lampiran_surat);

        return [
            'id' => $kegiatan->id,
            'jenis_surat' => $kegiatan->jenis_surat,
            'nomor' => $kegiatan->nomor,
            'nama_kegiatan' => $kegiatan->nama_kegiatan,
            'tanggal' => $kegiatan->tanggal?->format('Y-m-d'),
            'waktu' => $kegiatan->waktu,
            'tempat' => $kegiatan->tempat,
            'keterangan' => $kegiatan->keterangan,
            'sudah_disposisi' => (bool) $kegiatan->sudah_disposisi,
            'tampilkan_di_public' => (bool) $kegiatan->tampilkan_di_public,
            'batas_tindak_lanjut' => $kegiatan->batas_tindak_lanjut?->toISOString(),
            'surat_undangan' => $kegiatan->surat_undangan,
            'lampiran_surat' => $kegiatan->lampiran_surat,
            'surat_preview_url' => $kegiatan->surat_preview_url,
            'surat_view_url' => $kegiatan->surat_view_url,
            'lampiran_url' => $lampiranUrl,
            'personils' => $kegiatan->personils->map(fn (Personil $personil) => [
                'id' => $personil->id,
                'nama' => $personil->nama,
                'jabatan' => $personil->jabatan,
                'no_wa' => $personil->no_wa,
            ])->values(),
        ];
    }

    protected function storageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $segments = array_map('rawurlencode', explode('/', $path));
        $encodedPath = implode('/', $segments);

        return Storage::disk('public')->url($encodedPath);
    }
}
