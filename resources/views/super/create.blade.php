@extends('layouts.app')

@section('title', 'Kurum ekle · Etakit')

@section('content')
    <x-topbar title="Kurum ekle" :user="$user" menu="super" active="organizations" />

    <main class="container-xl py-4">
        <section class="card border-0 col-lg-7">
            <div class="card-body p-4">
                <h1 class="h4 mb-2">Yeni kurum</h1>
                <p class="text-secondary">Resmi kurum kodu öğretmen kaydı ve hızlı arama içindir. Tahta kayıt anahtarı otomatik üretilir.</p>
                <form method="post" action="{{ route('super.organizations.store') }}" class="mt-3">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="name">Kurum adı</label>
                        <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" required value="{{ old('name') }}">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="official_code">Resmi kurum kodu</label>
                        <input class="form-control @error('official_code') is-invalid @enderror" id="official_code" name="official_code" inputmode="numeric" required value="{{ old('official_code') }}" placeholder="8 haneli kod">
                        @error('official_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="admin_name">Yönetici adı</label>
                        <input class="form-control @error('admin_name') is-invalid @enderror" id="admin_name" name="admin_name" required value="{{ old('admin_name') }}">
                        @error('admin_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="admin_email">Yönetici e-posta</label>
                        <input class="form-control @error('admin_email') is-invalid @enderror" id="admin_email" name="admin_email" type="email" required value="{{ old('admin_email') }}">
                        @error('admin_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="admin_password">Yönetici parolası</label>
                        <input class="form-control @error('admin_password') is-invalid @enderror" id="admin_password" name="admin_password" type="password" required minlength="8">
                        @error('admin_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <button class="btn btn-primary" type="submit">Kurumu oluştur</button>
                </form>
            </div>
        </section>
    </main>
@endsection
