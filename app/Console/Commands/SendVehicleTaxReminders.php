<?php

namespace App\Console\Commands;

use App\Models\VehicleTax;
use App\Services\VehicleTaxReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendVehicleTaxReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vehicle-taxes:send-reminders {--date=} {--force : Kirim meskipun bukan pukul 08:00}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kirim pengingat WA untuk pajak kendaraan dinas (H-7, H-3, H0) pukul 08.00.';

    /**
     * Execute the console command.
     */
    public function handle(VehicleTaxReminderService $reminderService): int
    {
        $todayInput = $this->option('date');
        $today = $todayInput ? Carbon::parse($todayInput)->startOfDay() : Carbon::today();
        $now = Carbon::now();
        $totalSent = 0;

        $types = [
            'tahunan' => [
                'dateField' => 'tgl_pajak_tahunan',
                'lastField' => 'last_tahunan_reminder_sent_at',
                'lastStage' => 'last_tahunan_reminder_stage',
                'lastDate' => 'last_tahunan_reminder_for_date',
                'label' => '1 tahunan',
            ],
            'lima_tahunan' => [
                'dateField' => 'tgl_pajak_lima_tahunan',
                'lastField' => 'last_lima_tahunan_reminder_sent_at',
                'lastStage' => 'last_lima_tahunan_reminder_stage',
                'lastDate' => 'last_lima_tahunan_reminder_for_date',
                'label' => '5 tahunan',
            ],
        ];

        foreach ($types as $type => $config) {
            $vehicles = VehicleTax::query()->with('personil')->get();

            foreach ($vehicles as $vehicle) {
                $dueDate = $vehicle->{$config['dateField']};

                if (! $dueDate) {
                    continue;
                }

                $daysDiff = $today->diffInDays($dueDate, false);
                $stage = match ($daysDiff) {
                    7 => 'H-7',
                    3 => 'H-3',
                    0 => 'H0',
                    default => null,
                };

                if (! $stage) {
                    continue;
                }

                // Hindari kirim ulang untuk tanggal yang sama & stage yang sama
                if (
                    $vehicle->{$config['lastStage']} === $stage
                    && $vehicle->{$config['lastDate']}
                    && Carbon::parse($vehicle->{$config['lastDate']})->isSameDay($dueDate)
                ) {
                    continue;
                }

                // Kirim hanya pukul 08:00 WIB kecuali --force
                if (! $this->option('force')) {
                    $target = $today->copy()->setTime(8, 0);
                    if (! $now->isSameHour($target)) {
                        continue;
                    }
                }

                $result = $reminderService->send($vehicle, $type, $stage);

                if ($result['success'] ?? false) {
                    $totalSent++;
                    $this->info("Pengingat {$stage} pajak {$config['label']} dikirim untuk {$vehicle->plat_nomor_label}.");
                } else {
                    $this->warn(
                        "Pengingat {$stage} pajak {$config['label']} gagal untuk {$vehicle->plat_nomor_label}: "
                        . ($result['error'] ?? 'Tidak diketahui')
                    );
                }
            }
        }

        $this->info("Total pengingat terkirim: {$totalSent}");

        return self::SUCCESS;
    }
}
