<form
    id="teacher-login-form"
    class="card login-card border-0"
    data-otp="{{ url('/api/ogretmen/otp') }}"
    data-login="{{ url('/api/ogretmen/login') }}"
    data-panel="{{ route('teacher.panel') }}"
>
    <div class="card-body p-4 p-md-5">
        <p class="eyebrow mb-2">Etakit</p>
        <h1 class="h3 mb-2">Öğretmen girişi</h1>
        <p class="text-secondary mb-4">Telefonunuza gelen kod ile girin. Kod tahtayı açmaz; tahta karekod veya listedendir.</p>

        @if (session('status'))
            <p class="scan-result is-ok small" role="status">{{ session('status') }}</p>
        @endif

        <div id="phone-step">
            <div class="mb-4">
                <label class="form-label" for="phone">Cep telefonu</label>
                <input class="form-control" id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" required autofocus placeholder="05xx xxx xx xx">
            </div>
            <button class="btn btn-primary w-100" type="submit" data-action="otp">Kod gönder</button>
        </div>

        <div id="code-step" hidden>
            <div class="mb-3">
                <label class="form-label" for="code">Doğrulama kodu</label>
                <input class="form-control" id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="\d{6}">
            </div>
            <div class="form-check mb-4">
                <input class="form-check-input" id="remember" name="remember" type="checkbox" value="1">
                <label class="form-check-label" for="remember">Beni hatırla (1 yıl)</label>
            </div>
            <button class="btn btn-primary w-100" type="submit" data-action="login">Giriş</button>
            <button class="btn btn-outline-secondary w-100 mt-2" type="button" id="otp-back">Telefonu değiştir</button>
        </div>

        <p id="login-error" class="form-error small mb-0" role="alert"></p>
        <p class="fine small mt-3 mb-0">
            <a href="{{ route('teacher.register') }}">Kurum kodu ile kaydol</a>
            ·
            <a href="{{ route('privacy') }}">Gizlilik</a>
            ·
            <a href="{{ route('terms') }}">Kullanım koşulları</a>
        </p>
    </div>
</form>

@push('scripts')
    <script src="{{ asset('js/teacher-login.js') }}?v={{ filemtime(public_path('js/teacher-login.js')) }}"></script>
@endpush
