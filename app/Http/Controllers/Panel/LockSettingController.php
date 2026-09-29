<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\LockSetting;
use App\Services\EmergencyPin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class LockSettingController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('panel.settings', [
            'user' => $user,
            'organization' => $user->organization,
            'setting' => LockSetting::forOrganization((int) $user->organization_id),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'idle_minutes' => ['required', 'integer', 'min:1', 'max:180'],
            'lock_countdown_seconds' => ['required', 'integer', 'min:0', 'max:120'],
            'offline_grace_seconds' => ['required', 'integer', 'min:5', 'max:300'],
            'emergency_minutes' => ['required', 'integer', 'min:1', 'max:60'],
            'session_minutes' => ['required', 'integer', 'min:0', 'max:360'],
            'emergency_pin' => ['nullable', 'regex:/^\d{4,8}$/'],
            'attendance_sms_enabled' => ['nullable', 'boolean'],
            'attendance_sms_template' => ['nullable', 'required_if_accepted:attendance_sms_enabled', 'string', 'max:480'],
        ], [
            'attendance_sms_template.required_if_accepted' => 'Bildirim açıkken şablon gerekli.',
            'attendance_sms_template.max' => 'Bildirim şablonu en fazla 480 karakter olabilir.',
            'idle_minutes.required' => 'Boşta kalma süresi gerekli.',
            'idle_minutes.min' => 'Boşta kalma süresi en az 1 dakika olmalı.',
            'idle_minutes.max' => 'Boşta kalma süresi en fazla 180 dakika olabilir.',
            'lock_countdown_seconds.required' => 'Kilit geri sayımı gerekli.',
            'lock_countdown_seconds.min' => 'Kilit geri sayımı negatif olamaz.',
            'lock_countdown_seconds.max' => 'Kilit geri sayımı en fazla 120 saniye olabilir.',
            'offline_grace_seconds.required' => 'Bağlantı bekleme süresi gerekli.',
            'offline_grace_seconds.min' => 'Bağlantı bekleme süresi en az 5 saniye olmalı.',
            'offline_grace_seconds.max' => 'Bağlantı bekleme süresi en fazla 300 saniye olabilir.',
            'emergency_minutes.required' => 'Acil açılış süresi gerekli.',
            'emergency_minutes.min' => 'Acil açılış süresi en az 1 dakika olmalı.',
            'emergency_minutes.max' => 'Acil açılış süresi en fazla 60 dakika olabilir.',
            'session_minutes.required' => 'Oturum süresi gerekli.',
            'session_minutes.min' => 'Oturum süresi negatif olamaz.',
            'session_minutes.max' => 'Oturum süresi en fazla 360 dakika olabilir.',
            'emergency_pin.regex' => 'Acil parola 4 ile 8 rakam olmalı.',
        ]);

        $orgId = (int) $request->user()->organization_id;
        $current = LockSetting::forOrganization($orgId);
        $pin = trim((string) ($data['emergency_pin'] ?? ''));
        $hash = $current?->emergency_pin_hash;

        if ($pin !== '') {
            try {
                $hash = EmergencyPin::hash($pin);
            } catch (InvalidArgumentException $exception) {
                return back()->withErrors(['emergency_pin' => $exception->getMessage()])->withInput();
            }
        }

        $values = [
            'organization_id' => $orgId,
            'idle_seconds' => (int) $data['idle_minutes'] * 60,
            'lock_countdown_seconds' => (int) $data['lock_countdown_seconds'],
            'offline_grace_seconds' => (int) $data['offline_grace_seconds'],
            'emergency_seconds' => (int) $data['emergency_minutes'] * 60,
            'session_seconds' => (int) $data['session_minutes'] * 60,
            'emergency_pin_hash' => $hash,
            'attendance_sms_enabled' => $request->boolean('attendance_sms_enabled'),
            'attendance_sms_template' => trim((string) ($data['attendance_sms_template'] ?? '')) ?: null,
        ];

        if ($current) {
            $current->update($values);
        } else {
            LockSetting::query()->create($values);
        }

        return redirect()->route('panel.settings')->with('status', 'Ayarlar kaydedildi. Tahtalar kilit ayarlarını bir sonraki bağlantıda kullanır.');
    }
}
