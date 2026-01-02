<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PersonilDeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'platform' => ['nullable', 'string', 'max:32'],
        ]);

        $personil = $request->user();

        $token = PersonilDeviceToken::updateOrCreate(
            ['token' => $data['token']],
            [
                'personil_id' => $personil->id,
                'platform' => $data['platform'] ?? 'android',
                'last_used_at' => now(),
            ]
        );

        return response()->json([
            'id' => $token->id,
            'token' => $token->token,
            'platform' => $token->platform,
        ]);
    }

    public function destroy(Request $request, PersonilDeviceToken $deviceToken): JsonResponse
    {
        if ($deviceToken->personil_id !== $request->user()->id) {
            return response()->json(['message' => 'Tidak diizinkan.'], 403);
        }

        $deviceToken->delete();

        return response()->json(['message' => 'Token dihapus.']);
    }
}
