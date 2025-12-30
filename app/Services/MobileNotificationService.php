<?php

namespace App\Services;

use App\Models\Kegiatan;
use App\Models\LayananPublikRequest;
use App\Models\Personil;
use App\Models\WaInboxMessage;
use App\Notifications\MobileNotification;
use App\Support\RoleAccess;
use Illuminate\Support\Collection;

class MobileNotificationService
{
    /**
     * @return Collection<int, Personil>
     */
    protected function personilByRoleAccess(string $identifier): Collection
    {
        return Personil::query()
            ->whereNotNull('role')
            ->get()
            ->filter(fn (Personil $personil) => RoleAccess::canSeeNav($personil, $identifier))
            ->values();
    }

    /**
     * @return Collection<int, Personil>
     */
    protected function personilByJabatanLike(array $patterns): Collection
    {
        $patterns = array_filter(array_map('trim', $patterns));
        if ($patterns === []) {
            return collect();
        }

        return Personil::query()
            ->where(function ($query) use ($patterns) {
                foreach ($patterns as $pattern) {
                    $query->orWhere('jabatan', 'like', '%' . $pattern . '%');
                }
            })
            ->get();
    }

    public function notifyWaInbox(WaInboxMessage $message): void
    {
        $recipients = $this->personilByRoleAccess('filament.admin.resources.wa-inbox-messages');
        if ($recipients->isEmpty()) {
            return;
        }

        $sender = $message->sender_name ?: $message->sender_number;
        $title = 'Chat WA masuk';
        $body = $sender . ': ' . $message->message;

        $notification = new MobileNotification(
            $title,
            $body,
            'wa_inbox',
            ['wa_inbox_id' => $message->id]
        );

        foreach ($recipients as $personil) {
            $personil->notify($notification);
        }
    }

    public function notifyLayananPublikRegister(LayananPublikRequest $request): void
    {
        if (! $request->relationLoaded('layanan')) {
            $request->load('layanan');
        }

        $recipients = $this->personilByRoleAccess('filament.admin.resources.layanan-publik-register');
        if ($recipients->isEmpty()) {
            return;
        }

        $layanan = $request->layanan?->nama ?? 'Layanan Publik';
        $title = 'Permohonan layanan baru';
        $body = 'Kode: ' . $request->kode_register
            . ' · Antrian: ' . ($request->queue_number ?? '-')
            . ' · Layanan: ' . $layanan
            . ' · Pemohon: ' . $request->nama_pemohon;

        $notification = new MobileNotification(
            $title,
            $body,
            'layanan_register',
            ['layanan_request_id' => $request->id]
        );

        foreach ($recipients as $personil) {
            $personil->notify($notification);
        }
    }

    public function notifySuratMasukBaru(Kegiatan $kegiatan): void
    {
        if ($kegiatan->sudah_disposisi) {
            return;
        }

        if ($kegiatan->disposisi_notified_at) {
            return;
        }

        $patterns = (array) config('jadwal_notifications.camat_jabatan_like', []);
        $recipients = $this->personilByJabatanLike($patterns);
        if ($recipients->isEmpty()) {
            return;
        }

        $title = 'Surat masuk baru';
        $body = ($kegiatan->nama_kegiatan ?: 'Surat masuk')
            . ' · ' . ($kegiatan->tanggal?->format('d/m/Y') ?? '-');

        $notification = new MobileNotification(
            $title,
            $body,
            'surat_masuk_baru',
            ['kegiatan_id' => $kegiatan->id]
        );

        foreach ($recipients as $personil) {
            $personil->notify($notification);
        }

        $kegiatan->forceFill(['disposisi_notified_at' => now()])->saveQuietly();
    }

    public function notifyDisposisiEscalation(Kegiatan $kegiatan): void
    {
        if ($kegiatan->sudah_disposisi) {
            return;
        }

        if ($kegiatan->disposisi_escalated_at) {
            return;
        }

        $patterns = (array) config('jadwal_notifications.sekcam_jabatan_like', []);
        $recipients = $this->personilByJabatanLike($patterns);
        if ($recipients->isEmpty()) {
            return;
        }

        $title = 'Disposisi belum dilakukan';
        $body = ($kegiatan->nama_kegiatan ?: 'Surat masuk')
            . ' · ' . ($kegiatan->tanggal?->format('d/m/Y') ?? '-');

        $notification = new MobileNotification(
            $title,
            $body,
            'disposisi_terlambat',
            ['kegiatan_id' => $kegiatan->id]
        );

        foreach ($recipients as $personil) {
            $personil->notify($notification);
        }

        $kegiatan->forceFill(['disposisi_escalated_at' => now()])->saveQuietly();
    }
}
