@extends('layouts.app')

@include('partials.datatables')

@push('scripts')
    <script src="{{ asset('js/attendance-overview.js') }}?v={{ filemtime(public_path('js/attendance-overview.js')) }}"></script>
@endpush

@section('title', 'Yoklamalar · Etakit')

@section('content')
    <x-topbar title="Yoklamalar" :organization="$organization" :user="$user" menu="panel" active="attendance" />

    <main
        id="attendance-overview"
        class="container-xl py-4"
        data-data-url="{{ route('panel.attendance.data') }}"
        data-list-url="{{ $listUrl }}"
        data-lessons="{{ $lessons }}"
    >
        <section class="card border-0">
            <div class="card-body p-3 p-md-4 attendance-wrap attendance-overview-wrap">
                <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-3">
                    <div>
                        <h1 class="h4 mb-1">Günlük yoklama durumu</h1>
                        <p id="overview-date-label" class="text-secondary small mb-0"></p>
                    </div>
                    <div class="d-flex flex-wrap align-items-end gap-2">
                        <div>
                            <label class="form-label small mb-1" for="overview-date">Tarih</label>
                            <input id="overview-date" class="form-control form-control-sm" type="date" value="{{ $date }}" max="{{ $today }}">
                        </div>
                        <button id="overview-today" class="btn btn-outline-secondary btn-sm" type="button" data-today="{{ $today }}">Bugün</button>
                        <button id="overview-refresh" class="btn btn-primary btn-sm" type="button">Yenile</button>
                        <a id="overview-list" class="btn btn-outline-secondary btn-sm" href="{{ $listUrl }}?tarih={{ $date }}">Tüm liste</a>
                    </div>
                </div>

                <p id="overview-summary" class="small mb-2"></p>
                <p class="att-scroll-hint small text-secondary mb-2">Dersleri görmek için tabloyu sağa sola kaydırın.</p>

                <div>
                    <table id="overview-table" class="table teacher-table attendance-table overview-table align-middle w-100">
                        <thead>
                            <tr>
                                <th class="att-col-class">Sınıf</th>
                                @for ($lesson = 1; $lesson <= $lessons; $lesson++)
                                    <th class="text-center">{{ $lesson }}. ders</th>
                                @endfor
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </section>
    </main>
@endsection
