<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    /**
     * Form sederhana untuk menambah jadwal (dev/internal).
     */
    public function create(): View
    {
        return view('webhook.schedules.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id_group'    => ['required', 'string', 'max:191'],
            'title'       => ['required', 'string', 'max:255'],
            'starts_at'   => ['required', 'date'],
            'location'    => ['nullable', 'string', 'max:255'],
            'notes'       => ['nullable', 'string'],
            'is_disposed' => ['nullable', 'boolean'],
        ]);

        Schedule::create([
            'id_group'    => trim($data['id_group']),
            'title'       => $data['title'],
            'starts_at'   => $data['starts_at'],
            'location'    => $data['location'] ?? null,
            'notes'       => $data['notes'] ?? null,
            'is_disposed' => (bool) ($data['is_disposed'] ?? false),
        ]);

        return redirect()->route('webhook.schedules.create')
            ->with('status', 'Jadwal berhasil disimpan.');
    }
}
