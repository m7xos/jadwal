<?php

namespace App\Filament\Resources\WaInboxMessageResource\Pages;

use App\Filament\Resources\WaInboxMessageResource;
use App\Models\WaInboxMessage;
use App\Services\WaGatewayService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditWaInboxMessage extends EditRecord
{
    protected static string $resource = WaInboxMessageResource::class;

    protected function beforeFill(): void
    {
        $this->claimRecord();
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $user = auth()->user();
        if (! $user) {
            Notification::make()
                ->title('Gagal membalas')
                ->body('User tidak terdeteksi.')
                ->danger()
                ->send();

            throw new Halt();
        }

        /** @var WaInboxMessage $record */
        if ($record->assigned_to && $record->assigned_to !== $user->id) {
            Notification::make()
                ->title('Chat sudah diambil personil lain')
                ->warning()
                ->send();

            throw new Halt();
        }

        if ($record->replied_at) {
            Notification::make()
                ->title('Pesan sudah dibalas')
                ->warning()
                ->send();

            throw new Halt();
        }

        $reply = trim((string) ($data['reply_message'] ?? ''));
        if ($reply === '') {
            Notification::make()
                ->title('Balasan belum diisi')
                ->warning()
                ->send();

            throw new Halt();
        }

        $reply = $this->applySignature($reply, $user->nama ?? null);

        $waGateway = app(WaGatewayService::class);
        $result = $waGateway->sendPersonalText([$record->sender_number], $reply);

        if (! ($result['success'] ?? false)) {
            $detail = $result['error'] ?? 'Pengiriman gagal.';

            Notification::make()
                ->title('Gagal mengirim balasan')
                ->body($detail)
                ->danger()
                ->send();

            throw new Halt();
        }

        $record->reply_message = $reply;
        $record->replied_at = now();
        $record->replied_by = $user->id;

        if (! $record->assigned_to) {
            $record->assigned_to = $user->id;
            $record->assigned_at = now();
        }

        $record->status = WaInboxMessage::STATUS_REPLIED;
        $record->save();

        Notification::make()
            ->title('Balasan terkirim')
            ->success()
            ->send();

        return $record;
    }

    protected function claimRecord(): void
    {
        $userId = auth()->user()?->id;
        if (! $userId) {
            return;
        }

        /** @var WaInboxMessage $record */
        $record = $this->record;

        if ($record->assigned_to && $record->assigned_to !== $userId) {
            Notification::make()
                ->title('Chat sudah diambil personil lain')
                ->warning()
                ->send();

            $this->redirect(WaInboxMessageResource::getUrl());

            return;
        }

        if ($record->assigned_to) {
            return;
        }

        $updated = WaInboxMessage::query()
            ->whereKey($record->id)
            ->whereNull('assigned_to')
            ->update([
                'assigned_to' => $userId,
                'assigned_at' => now(),
                'status' => WaInboxMessage::STATUS_ASSIGNED,
            ]);

        if (! $updated) {
            $record->refresh();

            if ($record->assigned_to !== $userId) {
                Notification::make()
                    ->title('Chat sudah diambil personil lain')
                    ->warning()
                    ->send();

                $this->redirect(WaInboxMessageResource::getUrl());
            }
        } else {
            $record->refresh();
        }
    }

    protected function applySignature(string $message, ?string $name): string
    {
        $message = trim($message);
        $signature = $this->signatureFromName($name);

        if (! $signature) {
            return $message;
        }

        $message = preg_replace('/\\s-[A-Za-z]{2}\\s*$/', '', $message) ?? $message;
        $message = rtrim($message);

        return $message . ' -' . $signature;
    }

    protected function signatureFromName(?string $name): ?string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        $parts = preg_split('/\\s+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) >= 2) {
            return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
        }

        return mb_strtoupper(mb_substr($name, 0, 2));
    }
}
