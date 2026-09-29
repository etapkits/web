@extends('layouts.app')

@push('head')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
@endpush

@section('title', $canManage ? 'Tahtalar · Etakit' : 'Öğretmen · Etakit')

@section('content')
    <x-topbar :title="$organization?->name ?? 'Tahta kilidi'" :user="$user" :menu="$canManage ? 'panel' : 'teacher'" active="boards" />

    <main
        id="app"
        class="container-xl py-4 board-panel"
        data-boards-url="{{ $boardsUrl }}"
        data-unlock-url="{{ $unlockUrl }}"
        data-logout-url="{{ $logoutUrl }}"
        data-login-url="{{ $loginUrl }}"
        data-can-manage="{{ $canManage ? '1' : '0' }}"
        data-attendance-url="{{ $attendanceUrl ?? '' }}"
    >
        <div class="header-card p-4 mb-4">
            <div class="row align-items-center g-3">
                <div class="col-md-8">
                    <div class="d-flex align-items-center gap-3">
                        <div class="device-icon-wrapper board-heading-icon">
                            <i class="fa-solid fa-display"></i>
                        </div>
                        <div>
                            <h2 id="boards-title" class="h4 mb-0 fw-bold">Kayıtlı tahtalar</h2>
                            <small class="text-muted">Sistem yönetimi ve anlık tahta durumları</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 d-flex justify-content-md-end">
                    <button id="refresh" type="button" class="btn btn-pastel-main px-3 py-2">
                        <i class="fa-solid fa-rotate me-1"></i> Yenile
                    </button>
                </div>
            </div>

            <hr class="my-3 board-divider">

            <div class="row g-2 align-items-center">
                <div class="col-lg-7 d-flex flex-wrap gap-2">
                    <button class="btn filter-btn active px-3 py-1" type="button" data-filter="all">
                        Tümü (<span id="cnt-all">0</span>)
                    </button>
                    <button class="btn filter-btn px-3 py-1" type="button" data-filter="open">
                        <i class="fa-solid fa-circle-check text-success me-1"></i> Açık (<span id="cnt-open">0</span>)
                    </button>
                    <button class="btn filter-btn px-3 py-1" type="button" data-filter="locked">
                        <i class="fa-solid fa-lock text-warning me-1"></i> Kilitli (<span id="cnt-locked">0</span>)
                    </button>
                    <button class="btn filter-btn px-3 py-1" type="button" data-filter="closed">
                        <i class="fa-solid fa-circle-xmark text-secondary me-1"></i> Kapalı (<span id="cnt-closed">0</span>)
                    </button>
                </div>
                <div class="col-lg-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 rounded-start-3 board-search-icon">
                            <i class="fa-solid fa-magnifying-glass text-muted"></i>
                        </span>
                        <input id="board-search" type="search" class="form-control border-start-0 rounded-end-3" placeholder="Tahta adı ile ara..." autocomplete="off">
                    </div>
                </div>
            </div>

            <p id="panel-status" class="scan-result small mb-0" role="status"></p>
            <p id="board-loading" class="text-secondary mb-0 mt-3">Tahtalar yükleniyor.</p>
        </div>

        <div id="board-list" class="row g-3"></div>
    </main>
    <script src="{{ asset('js/panel.js') }}?v={{ filemtime(public_path('js/panel.js')) }}"></script>
@endsection
