<?php

namespace App\Http\Controllers;

use App\Models\Kegiatan;
use App\Models\Personil;
use Illuminate\Support\Str;

class KegiatanDisposisiController extends Controller
{
    public function show(Kegiatan $kegiatan)
    {
        $kegiatan->loadMissing('personils');

        $targets = $this->buildDisposisiTargets($kegiatan);
        $camat = $this->resolveCamatPersonil();

        return view('kegiatan.disposisi-print', [
            'kegiatan' => $kegiatan,
            'targets' => $targets['targets'],
            'lainnya' => $targets['lainnya'],
            'camat_nama' => $camat?->nama ?? '',
            'camat_pangkat' => $camat?->pangkat ?: ($camat?->golongan ?? ''),
            'camat_nip' => $camat?->nip ?? '',
        ]);
    }

    /**
     * @return array{targets: array<int, array{label: string, checked: bool}>, lainnya: string}
     */
    protected function buildDisposisiTargets(Kegiatan $kegiatan): array
    {
        $targets = [
            ['label' => 'Camat', 'patterns' => ['camat']],
            ['label' => 'Sekcam', 'patterns' => ['sekcam', 'sekretaris kecamatan', 'sekretaris']],
            ['label' => 'Subag Paten', 'patterns' => ['paten', 'subag paten', 'subbag paten']],
            ['label' => 'Seksi Pemer', 'patterns' => ['pemer', 'pemerintahan']],
            ['label' => 'Seksi Trantibum', 'patterns' => ['trantib', 'trantibum']],
            ['label' => 'Seksi Kesra Sos', 'patterns' => ['kesra', 'kesejahteraan', 'sos', 'sosial']],
            ['label' => 'Seksi Ekbang', 'patterns' => ['ekbang', 'ekonomi', 'pembangunan']],
        ];

        $lainnyaNames = [];

        foreach ($kegiatan->personils ?? collect() as $personil) {
            $jabatan = Str::lower((string) ($personil->jabatan ?? ''));
            $matched = false;

            foreach ($targets as $index => $target) {
                if ($jabatan === '') {
                    break;
                }

                foreach ($target['patterns'] as $pattern) {
                    if (Str::contains($jabatan, $pattern)) {
                        $targets[$index]['checked'] = true;
                        $matched = true;
                        break 2;
                    }
                }
            }

            if (! $matched) {
                $name = trim((string) ($personil->nama ?? ''));
                if ($name !== '') {
                    $lainnyaNames[] = $name;
                }
            }
        }

        $targets = array_map(function (array $target): array {
            return [
                'label' => $target['label'],
                'checked' => (bool) ($target['checked'] ?? false),
            ];
        }, $targets);

        $lainnya = '';
        if (! empty($lainnyaNames)) {
            $lainnya = implode(', ', array_values(array_unique($lainnyaNames)));
        }

        return [
            'targets' => $targets,
            'lainnya' => $lainnya,
        ];
    }

    protected function resolveCamatPersonil(): ?Personil
    {
        $patterns = array_filter((array) config('jadwal_notifications.camat_jabatan_like', []));

        $query = Personil::query();

        if (! empty($patterns)) {
            $query->where(function ($q) use ($patterns) {
                foreach ($patterns as $pattern) {
                    $pattern = trim((string) $pattern);
                    if ($pattern === '') {
                        continue;
                    }

                    $q->orWhere('jabatan', 'like', '%' . $pattern . '%');
                }
            });
        } else {
            $query->where('jabatan', 'like', '%camat%');
        }

        return $query->orderBy('nama')->first();
    }
}
