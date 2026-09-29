@extends('layouts.app')

@include('partials.datatables')

@push('scripts')
    <script src="{{ asset('js/attendance-list.js') }}?v={{ filemtime(public_path('js/attendance-list.js')) }}"></script>
@endpush

@section('title', 'Yoklama listesi · Etakit')

@section('content')
    <x-topbar title="Yoklama listesi" :organization="$organization" :user="$user" menu="panel" active="attendance" :attendance-date="$date" />

    <main
        id="attendance-list"
        class="container-xl py-4"
        data-data-url="{{ route('panel.attendance.list.data') }}"
        data-overview-url="{{ route('panel.attendance') }}"
    >
        <section class="card border-0">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-3">
                    <div>
                        <h1 class="h4 mb-1">Yoklama kayıtları</h1>
                        <p id="list-date-label" class="text-secondary small mb-0"></p>
                    </div>
                    <button id="list-copy" class="btn btn-outline-secondary btn-sm" type="button">Linki kopyala</button>
                </div>

                <form id="list-filters" class="row g-2 align-items-end mb-3" onsubmit="return false">
                    <div class="col-6 col-md-3 col-lg-2">
                        <label class="form-label small mb-1" for="f-tarih">Tarih</label>
                        <input id="f-tarih" name="tarih" class="form-control form-control-sm" type="date" value="{{ $date }}" max="{{ $today }}">
                    </div>
                    <div class="col-6 col-md-3 col-lg-2">
                        <label class="form-label small mb-1" for="f-sinif">Sınıf</label>
                        <select id="f-sinif" name="sinif" class="form-select form-select-sm">
                            <option value="">Tüm sınıflar</option>
                            @foreach ($classNames as $className)
                                <option value="{{ $className }}" @selected($className === $filters['class_name'])>{{ $className }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-3 col-lg-3">
                        <label class="form-label small mb-1" for="f-ogretmen">Öğretmen</label>
                        <select id="f-ogretmen" name="ogretmen" class="form-select form-select-sm">
                            <option value="">Tüm öğretmenler</option>
                            @foreach ($teachers as $id => $name)
                                <option value="{{ $id }}" @selected($id === $filters['teacher_id'])>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-3 col-lg-2">
                        <label class="form-label small mb-1" for="f-ders">Ders saati</label>
                        <select id="f-ders" name="ders" class="form-select form-select-sm">
                            <option value="">Tüm dersler</option>
                            @for ($lesson = 1; $lesson <= $lessons; $lesson++)
                                <option value="{{ $lesson }}" @selected($lesson === $filters['lesson'])>{{ $lesson }}. ders</option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-6 col-md-3 col-lg-2">
                        <label class="form-label small mb-1" for="f-durum">Durum</label>
                        <select id="f-durum" name="durum" class="form-select form-select-sm">
                            <option value="">Gelmedi ve geç</option>
                            <option value="absent" @selected($filters['status'] === 'absent')>Gelmedi</option>
                            <option value="late" @selected($filters['status'] === 'late')>Geç geldi</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3 col-lg-1">
                        <button id="f-clear" class="btn btn-outline-secondary btn-sm w-100" type="button">Temizle</button>
                    </div>
                </form>

                <div id="list-sessions" class="list-sessions mb-3" @if (($filters['class_name'] ?? '') === '') hidden @endif></div>

                <div class="table-responsive">
                    <table id="list-table" class="table teacher-table align-middle w-100">
                        <thead>
                            <tr>
                                <th>Sınıf</th>
                                <th>Ders</th>
                                <th>No</th>
                                <th>Ad Soyad</th>
                                <th>Durum</th>
                                <th>Öğretmen</th>
                                <th>Saat</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </section>
    </main>
@endsection
