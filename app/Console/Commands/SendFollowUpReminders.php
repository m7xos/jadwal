<?php

namespace App\Console\Commands;

use App\Models\FollowUpReminder;
use App\Services\FollowUpReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendFollowUpReminders extends Command
{
    protected $signature = 'reminders:send-follow-up';

    protected $description = 'Kirim pengingat pekerjaan lain yang belum diakui.';

    public function handle(FollowUpReminderService $reminderService): int
    {
        $now = Carbon::now();

        $dueReminders = FollowUpReminder::awaitingAck()
            ->where(function ($query) use ($now) {
                $query->whereNull('next_send_at')
                    ->orWhere('next_send_at', '<=', $now);
            })
            ->orderBy('next_send_at')
            ->get();

        if ($dueReminders->isEmpty()) {
            $this->info('Tidak ada pengingat yang perlu dikirim.');

            return self::SUCCESS;
        }

        foreach ($dueReminders as $reminder) {
            $result = $reminderService->send($reminder);
            $status = ($result['success'] ?? false) ? 'berhasil' : 'gagal';
            $error = $result['error'] ?? '';

            $this->line("Pengingat {$reminder->reminder_code} {$status}" . ($error ? " ({$error})" : ''));
        }

        return self::SUCCESS;
    }
}
