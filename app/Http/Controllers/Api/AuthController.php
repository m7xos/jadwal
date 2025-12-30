<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Personil;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'no_wa' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $normalized = PhoneNumber::normalize($data['no_wa']);
        if (! $normalized) {
            return response()->json(['message' => 'Nomor WA tidak valid.'], 422);
        }

        $personil = Personil::query()
            ->where('no_wa', $normalized)
            ->first();

        if (! $personil || ! Hash::check($data['password'], $personil->password)) {
            return response()->json(['message' => 'Login gagal.'], 401);
        }

        $tokenName = $request->userAgent() ?: 'android';
        $token = $personil->createToken($tokenName)->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'personil' => [
                'id' => $personil->id,
                'nama' => $personil->nama,
                'jabatan' => $personil->jabatan,
                'role' => $personil->role?->value ?? $personil->role,
                'no_wa' => $personil->no_wa,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        return response()->json(['message' => 'Logout sukses.']);
    }

    public function me(Request $request): JsonResponse
    {
        $personil = $request->user();

        return response()->json([
            'id' => $personil?->id,
            'nama' => $personil?->nama,
            'jabatan' => $personil?->jabatan,
            'role' => $personil?->role?->value ?? $personil?->role,
            'no_wa' => $personil?->no_wa,
        ]);
    }
}
