<?php

namespace App\Filament\Resources\FollowUpReminderResource\Pages;

use App\Filament\Resources\FollowUpReminderResource;
use App\Models\FollowUpReminder;
use App\Models\Personil;
use App\Services\FollowUpReminderService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateFollowUpReminder extends CreateRecord
{
    protected static string $resource = FollowUpReminderResource::class;
    protected array $createdRecords = [];

    protected function handleRecordCreation(array $data): Model
    {
        $personilIds = $data['personil_ids'] ?? null;
        unset($data['personil_ids']);

        if (is_array($personilIds)) {
            $ids = collect($personilIds)
                ->filter(fn ($id) => $id !== null && $id !== '')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($ids->isNotEmpty()) {
                $personils = Personil::query()
                    ->whereKey($ids->all())
                    ->get()
                    ->keyBy('id');

                if ($ids->count() > 1) {
                    $missing = [];
                    foreach ($ids as $id) {
                        $personil = $personils->get($id);
                        $noWa = trim((string) ($personil?->no_wa ?? ''));

                        if ($noWa === '') {
                            $missing[] = trim((string) ($personil?->nama ?? 'Personil #' . $id));
                        }
                    }

                    if (! empty($missing)) {
                        throw ValidationException::withMessages([
                            'personil_ids' => 'Nomor WA belum diisi untuk: ' . implode(', ', $missing),
                        ]);
                    }
                }

                $records = [];
                foreach ($ids as $id) {
                    $personil = $personils->get($id);
                    $noWa = trim((string) ($personil?->no_wa ?? ''));

                    if ($noWa === '') {
                        $noWa = trim((string) ($data['no_wa'] ?? ''));
                    }

                    if ($noWa === '') {
                        throw ValidationException::withMessages([
                            'no_wa' => 'Nomor WA wajib diisi untuk personil yang dipilih.',
                        ]);
                    }

                    $payload = $data;
                    $payload['personil_id'] = $id;
                    $payload['no_wa'] = $noWa;

                    $records[] = FollowUpReminder::create($payload);
                }

                $this->createdRecords = $records;

                return $records[0];
            }
        }

        if (! empty($data['personil_id']) && empty($data['no_wa'])) {
            $personil = Personil::find($data['personil_id']);
            if ($personil && $personil->no_wa) {
                $data['no_wa'] = $personil->no_wa;
            }
        }

        $record = FollowUpReminder::create($data);
        $this->createdRecords = [$record];

        return $record;
    }

    protected function afterCreate(): void
    {
        $records = $this->createdRecords;

        if (empty($records) && $this->record) {
            $records = [$this->record];
        }

        if (empty($records)) {
            return;
        }

        /** @var FollowUpReminderService $service */
        $service = app(FollowUpReminderService::class);

        foreach ($records as $record) {
            $service->send($record);
        }
    }
}
