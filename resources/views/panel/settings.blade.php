@extends('layouts.app')

@section('title', 'Kilit ayarları · Etakit')

@section('content')
    <x-topbar title="Kilit ayarları" :organization="$organization" :user="$user" menu="panel" active="settings" />

    <main class="container-xl py-4">
        <section class="card border-0 col-lg-8">
            <div class="card-body p-4">
                <h1 class="h4 mb-2">Tahta kilit ayarları</h1>
                @if ($setting)
                    <p class="text-secondary">Kayıtlı ayarlar tahtalara gider. Tahta bu değerleri sunucuya geri yazmaz.</p>
                @else
                    <p class="text-secondary">Henüz kayıt yok. Kaydedilene kadar her tahta kendi yerel ayarını kullanır; yerel ayar sunucuya gönderilmez.</p>
                @endif

                @if (session('status'))
                    <p class="scan-result small" role="status">{{ session('status') }}</p>
                @endif

                <form method="post" action="{{ route('panel.settings.update') }}" class="mt-3">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="session_minutes">Oturum süresi (dakika)</label>
                        <input class="form-control @error('session_minutes') is-invalid @enderror" id="session_minutes" name="session_minutes" type="number" min="0" max="360" required value="{{ old('session_minutes', $setting ? intdiv((int) ($setting->session_seconds ?? 0), 60) : 40) }}">
                        @error('session_minutes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Kullanıcı giriş yapınca sayaç başlar. Süre bitince tahta oturumu kapanır. 0, sayacı kapatır.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="idle_minutes">Boşta kalınca kilitle (dakika)</label>
                        <input class="form-control @error('idle_minutes') is-invalid @enderror" id="idle_minutes" name="idle_minutes" type="number" min="1" max="180" required value="{{ old('idle_minutes', $setting ? intdiv($setting->idle_seconds, 60) : 10) }}">
                        @error('idle_minutes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="lock_countdown_seconds">Kilit geri sayımı (saniye)</label>
                        <input class="form-control @error('lock_countdown_seconds') is-invalid @enderror" id="lock_countdown_seconds" name="lock_countdown_seconds" type="number" min="0" max="120" required value="{{ old('lock_countdown_seconds', $setting?->lock_countdown_seconds ?? 10) }}">
                        @error('lock_countdown_seconds')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="offline_grace_seconds">Bağlantı kopunca bekle (saniye)</label>
                        <input class="form-control @error('offline_grace_seconds') is-invalid @enderror" id="offline_grace_seconds" name="offline_grace_seconds" type="number" min="5" max="300" required value="{{ old('offline_grace_seconds', $setting?->offline_grace_seconds ?? 45) }}">
                        @error('offline_grace_seconds')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="emergency_pin">Acil parola</label>
                        <input class="form-control @error('emergency_pin') is-invalid @enderror" id="emergency_pin" name="emergency_pin" type="password" inputmode="numeric" autocomplete="new-password" placeholder="{{ $setting?->emergency_pin_hash ? 'Kayıtlı parola korunur' : '4-8 rakam, isteğe bağlı' }}">
                        @error('emergency_pin')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="emergency_minutes">Acil açılış süresi (dakika)</label>
                        <input class="form-control @error('emergency_minutes') is-invalid @enderror" id="emergency_minutes" name="emergency_minutes" type="number" min="1" max="60" required value="{{ old('emergency_minutes', $setting ? intdiv($setting->emergency_seconds, 60) : 5) }}">
                        @error('emergency_minutes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <h2 class="h5 mb-3">Yoklama bildirimi</h2>
                    <div class="form-check mb-3">
                        <input type="hidden" name="attendance_sms_enabled" value="0">
                        <input class="form-check-input" id="attendance_sms_enabled" name="attendance_sms_enabled" type="checkbox" value="1" @checked(old('attendance_sms_enabled', $setting?->attendance_sms_enabled))>
                        <label class="form-check-label" for="attendance_sms_enabled">Yoklama alınınca öğrenciye bildirim gönder</label>
                        <div class="form-text">"Gelmedi" veya "Geç geldi" işaretlenen öğrencinin iletişim telefonuna WhatsApp ile bildirim gider.</div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="attendance_sms_template">Yoklama bildirim şablonu</label>
                        <textarea class="form-control @error('attendance_sms_template') is-invalid @enderror" id="attendance_sms_template" name="attendance_sms_template" rows="3" maxlength="480">{{ old('attendance_sms_template', $setting?->attendance_sms_template ?? \App\Models\LockSetting::DEFAULT_ATTENDANCE_SMS_TEMPLATE) }}</textarea>
                        @error('attendance_sms_template')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Kullanılabilir alanlar: {ogrenci}, {sinif}, {ders}, {tarih}, {durum}, {kurum}</div>
                    </div>
                    <button class="btn btn-primary" type="submit">Kaydet</button>
                </form>
            </div>
        </section>
    </main>
@endsection
