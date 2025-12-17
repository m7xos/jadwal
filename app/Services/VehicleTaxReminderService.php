<?php

namespace App\Services;

use App\Models\VehicleTax;
use App\Models\VehicleTaxSetting;
use App\Models\VehicleTaxReminderLog;
use App\Support\PhoneNumber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class VehicleTaxReminderService
{
    public function __construct(protected WaGatewayService $waGateway)
    {
    }

    /**
     * Kirim pengingat pajak untuk 1 tahunan atau 5 tahunan.
     *
     * @param  string  $type  Nilai: "tahunan" atau "lima_tahunan"
     * @return array{success: bool, error: string|null, response: mixed, type: string, targets: array}
     */
    public function send(VehicleTax $vehicle, string $type, string $stage = null): array
    {
        $type = $type === 'lima_tahunan' ? 'lima_tahunan' : 'tahunan';

        if (! $this->waGateway->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Konfigurasi WA Gateway belum lengkap',
                'response' => null,
                'type' => $type,
                'targets' => [],
            ];
        }

        $vehicle->loadMissing('personil');

        $dueDate = $vehicle->dueDateFor($type);

        if (! $dueDate) {
            return [
                'success' => false,
                'error' => 'Tanggal pajak belum diisi',
                'response' => null,
                'type' => $type,
                'targets' => [],
            ];
        }

        // Jangan kirim jika sudah lunas
        if ($vehicle->isPaid()) {
            return [
                'success' => false,
                'error' => 'Status pajak sudah LUNAS',
                'response' => null,
                'type' => $type,
                'targets' => [],
            ];
        }

        $dueDate = Carbon::parse($dueDate);

        $setting = VehicleTaxSetting::current();

        $targets = [];

        $pemegangNo = PhoneNumber::normalize($vehicle->personil?->no_wa);
        if ($pemegangNo) {
            $targets[] = $pemegangNo;
        }

        $pengurusNo = $setting->resolved_pengurus_barang_no_wa;
        if ($pengurusNo) {
            $targets[] = $pengurusNo;
        }

        $targets = array_values(array_unique(array_filter($targets)));

        if (empty($targets)) {
            return [
                'success' => false,
                'error' => 'Tidak ada nomor WA tujuan (pemegang atau pengurus barang).',
                'response' => null,
                'type' => $type,
                'targets' => [],
            ];
        }

        $message = $this->buildMessage(
            $vehicle,
            $type,
            $dueDate,
            $setting->resolved_pengurus_barang_nama,
        );

        $result = $this->waGateway->sendPersonalText($targets, $message);
        $result['type'] = $type;
        $result['targets'] = $targets;

        $log = new VehicleTaxReminderLog([
            'vehicle_tax_id' => $vehicle->id,
            'type' => $type,
            'stage' => $stage,
            'status' => ($result['success'] ?? false) ? 'success' : 'failed',
            'error_message' => $result['error'] ?? null,
            'response' => $result['response'] ?? null,
            'sent_at' => ($result['success'] ?? false) ? Carbon::now() : null,
        ]);
        $log->save();

        if ($result['success'] ?? false) {
            $field = $vehicle->lastReminderFieldFor($type);

            $payload = [];
            if ($field) {
                $payload[$field] = Carbon::now();
            }

            if ($type === 'tahunan') {
                $payload['last_tahunan_reminder_stage'] = $stage;
                $payload['last_tahunan_reminder_for_date'] = $dueDate->toDateString();
            } else {
                $payload['last_lima_tahunan_reminder_stage'] = $stage;
                $payload['last_lima_tahunan_reminder_for_date'] = $dueDate->toDateString();
            }

            if (! empty($payload)) {
                $vehicle->forceFill($payload)->save();
            }
        } else {
            Log::warning('Gagal mengirim pengingat pajak kendaraan.', [
                'vehicle_tax_id' => $vehicle->id,
                'type' => $type,
                'stage' => $stage,
                'error' => $result['error'] ?? null,
            ]);
        }

        return $result;
    }

    protected function buildMessage(
        VehicleTax $vehicle,
        string $type,
        Carbon $dueDate,
        ?string $pengurusNama
    ): string {
        $typeLabel = $type === 'lima_tahunan' ? '5 tahunan' : '1 tahunan';
        $jenis = ucfirst((string) ($vehicle->jenis_kendaraan ?? '-'));
        $plat = strtoupper((string) ($vehicle->plat_nomor ?? '-'));
        $pemegang = $vehicle->personil?->nama ?? '-';
        $pengurusLabel = $pengurusNama ? ' (' . $pengurusNama . ')' : '';

        $lines = [];
        $lines[] = '*Pengingat Pembayaran Pajak (' . $typeLabel . ')*';
        $lines[] = '';
        $lines[] = 'Bapak/Ibu *' . $pemegang . '*';
        $lines[] = 'Kendaraan ' . $jenis . ' ' . $plat . ' sudah masuk waktu perpanjangan pajak ' . $typeLabel . ', Mohon segera berkoordinasi dengan pengurus barang' . $pengurusLabel . '.';
        $lines[] = '';
        $lines[] = 'batas waktu pembayaran pajak: *' . $dueDate->locale('id')->isoFormat('D MMMM Y') . '*';
        $lines[] = '';
        $lines[] = 'Terima Kasih';

        return implode("\n", $lines);
    }
}
