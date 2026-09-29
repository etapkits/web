@extends('layouts.app')

@section('title', 'İdare girişi · Etakit')

@section('content')
    <main class="login-screen d-flex align-items-center justify-content-center px-3 py-4">
        <form id="login-form" class="card login-card border-0" data-url="{{ url('/api/panel/login') }}" data-panel="{{ route('panel') }}">
            <div class="card-body p-4 p-md-5">
                <p class="eyebrow mb-2">Etakit</p>
                <h1 class="h3 mb-2">Kurum yöneticisi</h1>
                <p class="text-secondary mb-4">E-posta ve parola ile kurum panelinize girin.</p>

                <div class="mb-3">
                    <label class="form-label" for="email">E-posta</label>
                    <input class="form-control" id="email" name="email" type="email" autocomplete="username" required autofocus>
                </div>

                <div class="mb-4">
                    <label class="form-label" for="password">Parola</label>
                    <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required>
                </div>

                <button class="btn btn-primary w-100" type="submit">Giriş</button>
                <p id="login-error" class="form-error small mb-0" role="alert"></p>
                <p class="fine small mt-3 mb-0">
                    <a href="{{ route('home') }}">Öğretmen girişi</a>
                </p>
            </div>
        </form>
    </main>
    <script src="{{ asset('js/login.js') }}"></script>
@endsection
