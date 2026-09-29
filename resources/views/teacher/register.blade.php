@extends('layouts.app')

@section('title', 'Öğretmen kaydı · Etakit')

@section('content')
    <main class="login-screen d-flex align-items-center justify-content-center px-3 py-4">
        <form method="post" action="{{ route('teacher.register.store') }}" class="card login-card border-0">
            @csrf
            <div class="card-body p-4 p-md-5">
                <p class="eyebrow mb-2">Etakit</p>
                <h1 class="h3 mb-2">Öğretmen kaydı</h1>
                <p class="text-secondary mb-4">Resmi kurum kodunuzla kaydolun. İdare onaylayınca telefonunuzla giriş açılır.</p>

                <div class="mb-3">
                    <label class="form-label" for="official_code">Kurum kodu</label>
                    <input class="form-control @error('official_code') is-invalid @enderror" id="official_code" name="official_code" inputmode="numeric" required value="{{ old('official_code') }}" placeholder="8 haneli kod">
                    @error('official_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="first_name">Ad</label>
                    <input class="form-control @error('first_name') is-invalid @enderror" id="first_name" name="first_name" required value="{{ old('first_name') }}">
                    @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="last_name">Soyad</label>
                    <input class="form-control @error('last_name') is-invalid @enderror" id="last_name" name="last_name" required value="{{ old('last_name') }}">
                    @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-4">
                    <label class="form-label" for="phone">Cep telefonu</label>
                    <input class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" type="tel" required value="{{ old('phone') }}" placeholder="05xx xxx xx xx">
                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <button class="btn btn-primary w-100" type="submit">Kaydol</button>
                <p class="fine small mt-3 mb-0">
                    <a href="{{ route('home') }}">Öğretmen girişi</a>
                </p>
            </div>
        </form>
    </main>
@endsection
