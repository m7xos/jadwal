<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Personil;
use App\Models\WaInboxMessage;
use App\Services\WaGatewayService;
use App\Support\RoleAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WaInboxController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->ensureAccess($request->user());

        $query = WaInboxMessage::query()->latest('received_at');

        $status = $request->input('status');
        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        $assignedToMe = $request->input('assigned_to_me');
        if (filter_var($assignedToMe, FILTER_VALIDATE_BOOLEAN) && $request->user()) {
            $query->where('assigned_to', $request->user()->id);
        }

        $perPage = (int) $request->input('per_page', 20);
        $perPage = $perPage > 0 && $perPage <= 100 ? $perPage : 20;

        $messages = $query->paginate($perPage);

        $messages->getCollection()->transform(fn (WaInboxMessage $message) => $this->formatMessage($message));

        return response()->json($messages);
    }

    public function reply(Request $request, WaInboxMessage $message, WaGatewayService $waGateway): JsonResponse
    {
        $this->ensureAccess($request->user());

        $personil = $request->user();

        if ($message->assigned_to && $message->assigned_to !== $personil->id) {
            return response()->json(['message' => 'Chat sudah diambil personil lain.'], 409);
        }

        if ($message->replied_at) {
            return response()->json(['message' => 'Pesan sudah dibalas.'], 409);
        }

        $data = $request->validate([
            'reply_message' => ['required', 'string'],
        ]);

        $reply = trim($data['reply_message']);
        if ($reply === '') {
            return response()->json(['message' => 'Balasan belum diisi.'], 422);
        }

        $reply = $this->applySignature($reply, $personil->nama ?? null);

        $result = $waGateway->sendPersonalText([$message->sender_number], $reply);
        if (! ($result['success'] ?? false)) {
            return response()->json([
                'message' => 'Gagal mengirim balasan.',
                'detail' => $result['error'] ?? null,
            ], 500);
        }

        $message->reply_message = $reply;
        $message->replied_at = now();
        $message->replied_by = $personil->id;

        if (! $message->assigned_to) {
            $message->assigned_to = $personil->id;
            $message->assigned_at = now();
        }

        $message->status = WaInboxMessage::STATUS_REPLIED;
        $message->save();

        return response()->json($this->formatMessage($message));
    }

    protected function ensureAccess(?Personil $personil): void
    {
        if (! $personil || ! RoleAccess::canSeeNav($personil, 'filament.admin.resources.wa-inbox-messages')) {
            abort(403, 'Tidak diizinkan.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatMessage(WaInboxMessage $message): array
    {
        return [
            'id' => $message->id,
            'sender_number' => $message->sender_number,
            'sender_name' => $message->sender_name,
            'message' => $message->message,
            'received_at' => $message->received_at?->toISOString(),
            'status' => $message->status,
            'assigned_to' => $message->assigned_to,
            'assigned_at' => $message->assigned_at?->toISOString(),
            'replied_by' => $message->replied_by,
            'replied_at' => $message->replied_at?->toISOString(),
            'reply_message' => $message->reply_message,
            'meta' => $message->meta,
        ];
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
            return Str::upper(Str::substr($parts[0], 0, 1) . Str::substr($parts[1], 0, 1));
        }

        return Str::upper(Str::substr($name, 0, 2));
    }
}
