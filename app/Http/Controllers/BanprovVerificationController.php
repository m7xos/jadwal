<?php

namespace App\Http\Controllers;

use App\Models\BanprovVerification;
use App\Services\BanprovVerificationGenerator;

class BanprovVerificationController extends Controller
{
    public function print(BanprovVerification $verification, BanprovVerificationGenerator $generator)
    {
        $user = auth()->user();

        if (! $user) {
            abort(403);
        }

        if (! $user->isAdmin()) {
            $akronim = strtolower(trim((string) ($user->jabatan_akronim ?? '')));
            if ($akronim !== 'ekbang') {
                abort(403);
            }
        }

        $result = $generator->generate($verification);

        return response()->download(
            $result['path'],
            basename($result['path']),
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ]
        );
    }
}
