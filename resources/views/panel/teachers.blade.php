@extends('layouts.app')

@include('partials.datatables')

@push('scripts')
    <script src="{{ asset('js/teachers.js') }}?v={{ filemtime(public_path('js/teachers.js')) }}"></script>
@endpush

@section('title', 'Öğretmenler · Etakit')

@section('content')
    <x-topbar title="Öğretmenler" :organization="$organization" :user="$user" menu="panel" active="teachers" />

    <main class="container-xl py-4">
        <div class="row g-4">
            <div class="col-lg-4">
                <section class="card border-0">
                    <div class="card-body p-4">
                        <h1 class="h4 mb-2">Öğretmen ekle</h1>
                        <p class="text-secondary">Kayıt doğrudan onaylı olur. Bir telefon yalnızca bir kuruma aittir.</p>
                        <form method="post" action="{{ route('panel.teachers.store') }}" class="mt-3">
                            @csrf
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
                            <button class="btn btn-primary" type="submit">Kaydet</button>
                        </form>
                    </div>
                </section>
            </div>
            <div class="col-lg-8">
                <section id="teachers" class="card border-0" data-data-url="{{ route('panel.teachers.data') }}">
                    <div class="card-body p-4">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-2">
                            <h2 class="h4 mb-0">Kayıtlı öğretmenler</h2>
                            <select id="teacher-status-filter" class="form-select form-select-sm dt-filter" aria-label="Durum filtresi">
                                <option value="">Tüm durumlar</option>
                                <option value="pending">Onay bekliyor</option>
                                <option value="approved">Onaylı</option>
                            </select>
                        </div>
                        <p id="teacher-status" class="scan-result small {{ session('status') ? 'is-ok' : '' }}" role="status">{{ session('status') }}</p>
                        <div class="table-responsive">
                            <table id="teacher-table" class="table teacher-table align-middle w-100">
                                <thead>
                                    <tr>
                                        <th>Ad</th>
                                        <th>Soyad</th>
                                        <th>Telefon</th>
                                        <th>Durum</th>
                                        <th class="text-end">İşlem</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </main>
@endsection
