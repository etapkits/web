@props([
    'title',
    'organization' => null,
    'menu' => null,
    'active' => null,
    'user' => null,
    'attendanceDate' => null,
])

@php
    $links = match ($menu) {
        'panel' => [
            'boards' => ['Tahtalar', route('panel'), 'fa-display'],
            'teachers' => ['Öğretmenler', route('panel.teachers'), 'fa-chalkboard-user'],
            'students' => ['Öğrenciler', route('panel.students'), 'fa-user-graduate'],
            'attendance' => ['Yoklamalar', route('panel.attendance', array_filter(['date' => $attendanceDate])), 'fa-clipboard-check'],
            'settings' => ['Kilit ayarları', route('panel.settings'), 'fa-lock'],
        ],
        'teacher' => [
            'boards' => ['Tahtalar', route('teacher.panel'), 'fa-display'],
            'attendance' => ['Yoklama', route('teacher.attendance'), 'fa-clipboard-check'],
        ],
        'super' => [
            'organizations' => ['Kurumlar', route('super.organizations'), 'fa-building'],
        ],
        default => [],
    };

    [$logoutUrl, $loginUrl, $unlockUrl] = match ($menu) {
        'panel' => [url('/api/panel/logout'), route('login'), url('/api/panel/unlock')],
        'teacher' => [url('/api/ogretmen/logout'), route('home'), url('/api/ogretmen/unlock')],
        'super' => [url('/api/super/logout'), route('super.login'), null],
        default => [null, null, null],
    };
@endphp

<header class="pastel-nav">
    <div class="container-xl topbar-inner py-3">
        <div class="topbar-brand">
            <p class="eyebrow mb-0">Etakit</p>
            <p class="topbar-title mb-0">
                {{ $title }}
                @if ($organization)
                    <small class="topbar-org">· {{ $organization->name }}</small>
                @endif
            </p>
        </div>

        <button class="topbar-toggle" type="button" aria-controls="topbar-menu" aria-expanded="false" aria-label="Menüyü aç">
            <span></span><span></span><span></span>
        </button>

        <nav id="topbar-menu" class="topbar-menu" aria-label="Ana menü">
            @foreach ($links as $key => [$label, $url, $icon])
                <a @class(['btn btn-outline-secondary btn-sm topbar-link', 'active' => $key === $active]) href="{{ $url }}"{!! $key === $active ? ' aria-current="page"' : '' !!}><i class="fa-solid {{ $icon }}" aria-hidden="true"></i>{{ $label }}</a>
            @endforeach
            <div class="topbar-user">
                <button class="btn btn-sm topbar-user-toggle" type="button" aria-controls="topbar-user-menu" aria-expanded="false" aria-haspopup="menu">
                    <i class="fa-solid fa-circle-user" aria-hidden="true"></i>
                    <span class="topbar-user-name">{{ $user?->name ?? 'Hesap' }}</span>
                    <i class="fa-solid fa-chevron-down topbar-caret" aria-hidden="true"></i>
                </button>
                <div id="topbar-user-menu" class="topbar-user-menu" role="menu" hidden>
                    {{ $slot }}
                    @if ($menu === 'teacher')
                        <button class="topbar-user-item topbar-forget" type="button" role="menuitem" data-url="{{ url('/api/ogretmen/forget') }}" data-login="{{ route('home') }}"><i class="fa-solid fa-user-xmark" aria-hidden="true"></i>Beni unut</button>
                    @endif
                    @if ($logoutUrl)
                        <button class="topbar-user-item topbar-logout" type="button" role="menuitem" data-url="{{ $logoutUrl }}" data-login="{{ $loginUrl }}" data-closed="{{ route('signed-out', ['giris' => $menu]) }}"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>Çıkış</button>
                    @endif
                </div>
            </div>
        </nav>
    </div>
</header>

@if ($unlockUrl && $logoutUrl)
    <nav class="mobile-nav" aria-label="Alt menü">
        <a @class(['mobile-nav-btn', 'active' => $active === 'boards']) href="{{ $links['boards'][1] }}"{!! $active === 'boards' ? ' aria-current="page"' : '' !!}>
            <i class="fa-solid fa-display" aria-hidden="true"></i>
            <span>Tahtalar</span>
        </a>
        <button id="qr-open" class="mobile-nav-btn" type="button">
            <i class="fa-solid fa-qrcode" aria-hidden="true"></i>
            <span>QR okut</span>
        </button>
        <button class="mobile-nav-btn topbar-logout" type="button" data-url="{{ $logoutUrl }}" data-login="{{ $loginUrl }}" data-closed="{{ route('signed-out', ['giris' => $menu]) }}">
            <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
            <span>Çıkış</span>
        </button>
    </nav>

    <dialog id="qr-dialog" class="qr-dialog" aria-labelledby="qr-dialog-title" data-unlock-url="{{ $unlockUrl }}" data-login-url="{{ $loginUrl }}">
        <form method="dialog" class="qr-dialog-bar">
            <h2 id="qr-dialog-title" class="h5 mb-0">QR okut</h2>
            <button class="qr-dialog-close" type="submit" aria-label="Kapat"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </form>
        <p class="text-secondary small mb-3">Tahtanın kilit ekranındaki kodu kameraya tutun.</p>
        <div class="viewfinder">
            <video id="qr-camera" playsinline autoplay muted hidden></video>
        </div>
        <p id="qr-note" class="note small mt-3 mb-0" hidden>Kamera yalnızca şifreli (HTTPS) adreste çalışır.</p>
        <p id="qr-result" class="scan-result small mb-0" role="status"></p>
    </dialog>
@endif

@once
    @push('head')
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    @endpush

    @push('scripts')
        <script src="{{ asset('js/topbar.js') }}?v={{ filemtime(public_path('js/topbar.js')) }}"></script>
        @if ($unlockUrl)
            <script src="{{ asset('js/jsQR.js') }}"></script>
            <script src="{{ asset('js/qr-scan.js') }}?v={{ filemtime(public_path('js/qr-scan.js')) }}"></script>
        @endif
    @endpush
@endonce
