<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    /** Nilai bawaan profil studio (dipakai bila belum pernah disimpan). */
    public const DEFAULTS = [
        'studio_name' => 'Naro Capture',
        'admin_name' => 'Naro Capture',
        'studio_email' => 'narocapture@gmail.com',
        'studio_whatsapp' => '',
        'studio_location' => 'Jakarta, Indonesia',
        'timezone' => 'WIB',
    ];

    public function index()
    {
        $settings = [];
        foreach (self::DEFAULTS as $key => $default) {
            $settings[$key] = Setting::get($key, $default);
        }

        return view('admin.pengaturan', compact('settings'));
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'studio_name' => 'required|string|max:100',
            'admin_name' => 'required|string|max:100',
            'studio_email' => 'required|email|max:150',
            'studio_whatsapp' => 'nullable|string|max:50',
            'studio_location' => 'nullable|string|max:150',
            'timezone' => 'required|in:WIB,WITA,WIT',
        ], [
            'studio_name.required' => 'Nama studio wajib diisi.',
            'admin_name.required' => 'Nama admin wajib diisi.',
            'studio_email.required' => 'Email studio wajib diisi.',
            'studio_email.email' => 'Format email studio tidak valid.',
        ]);

        foreach ($data as $key => $value) {
            Setting::put($key, $value);
        }

        return redirect()->route('admin.pengaturan')->with('success', 'Profil studio berhasil disimpan.');
    }
}
