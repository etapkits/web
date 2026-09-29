@extends('layouts.app')

@section('title', 'Kurum düzenle · Etakit')

@section('content')
    <x-topbar title="Kurum düzenle" :user="$user" menu="super" active="organizations" />

    <main class="container-xl py-4">
        <section class="card border-0 col-lg-7">
            <div class="card-body p-4">
                <h1 class="h4 mb-2">{{ $organization->name }}</h1>
                <p class="text-secondary">Pasif kurumun yöneticisi ve öğretmenleri giriş yapamaz; yeni tahta ve öğretmen kaydı alınmaz.</p>
                <form method="post" action="{{ route('super.organizations.update', $organization) }}" class="mt-3">
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label class="form-label" for="name">Kurum adı</label>
                        <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" required value="{{ old('name', $organization->name) }}">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="official_code">Resmi kurum kodu</label>
                        <input class="form-control @error('official_code') is-invalid @enderror" id="official_code" name="official_code" inputmode="numeric" required value="{{ old('official_code', $organization->official_code) }}">
                        @error('official_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-check form-switch mb-4">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" id="is_active" name="is_active" type="checkbox" role="switch" value="1" @checked(old('is_active', $organization->is_active))>
                        <label class="form-check-label" for="is_active">Kurum aktif</label>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary" type="submit">Kaydet</button>
                        <a class="btn btn-outline-secondary" href="{{ route('super.organizations') }}">Vazgeç</a>
                    </div>
                </form>
            </div>
        </section>
    </main>
@endsection
