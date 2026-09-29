@extends('layouts.app')

@include('partials.datatables')

@push('scripts')
    <script src="{{ asset('js/attendance.js') }}?v={{ filemtime(public_path('js/attendance.js')) }}"></script>
@endpush

@section('title', 'Yoklama · Etakit')

@section('content')
    <x-topbar title="Yoklama" :organization="$organization" :user="$user" :menu="$menu" active="attendance" />

    <main
        id="attendance"
        class="container-xl py-4"
        data-data-url="{{ $dataUrl }}"
        data-store-url="{{ $storeUrl }}"
        data-lessons="{{ $lessons }}"
    >
        <section class="card border-0 mb-4">
            <div class="card-body p-4">
                <div class="row g-3 align-items-end">
                    <div class="col-md-5 col-lg-4">
                        <label class="form-label fw-semibold" for="attendance-class">Sınıf seçin</label>
                        <select id="attendance-class" class="form-select">
                            <option value="">Sınıf seçin…</option>
                            @foreach ($classNames as $className)
                                <option value="{{ $className }}" @selected($className === $selected)>{{ $className }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-7 col-lg-8">
                        <p class="mb-1 fw-semibold">{{ $today }}</p>
                        <p class="text-secondary small mb-0">
                            Düğmeye bir kez dokunun: <span class="att-chip att-absent">Gelmedi</span>,
                            bir daha: <span class="att-chip att-late">Geç</span>, bir daha: boş (geldi).
                            Her ders için yoklama bir kez kaydedilir; kaydedilen yoklama değiştirilemez.
                        </p>
                    </div>
                </div>
                @if ($classNames === [])
                    <p class="scan-result small mb-0">Kurumda kayıtlı öğrenci yok. İdare öğrenci listesini yüklemeli.</p>
                @endif
                <p id="attendance-status" class="scan-result small mb-0" role="status"></p>
            </div>
        </section>

        <section id="attendance-card" class="card border-0" hidden>
            <div class="card-body p-3 p-md-4 attendance-wrap">
                <p class="att-scroll-hint small text-secondary mb-2">Dersleri görmek için tabloyu sağa sola kaydırın.</p>
                <div>
                    <table id="attendance-table" class="table teacher-table attendance-table align-middle w-100">
                        <thead>
                            <tr>
                                <th class="att-col-index">Sıra</th>
                                <th class="att-col-no">No</th>
                                <th class="att-name">Ad Soyad</th>
                                @for ($lesson = 1; $lesson <= $lessons; $lesson++)
                                    <th class="text-center att-lesson-head" data-lesson="{{ $lesson }}">
                                        <div class="att-head-title">{{ $lesson }}. ders</div>
                                        <button class="btn btn-primary btn-sm att-save" type="button" data-lesson="{{ $lesson }}" hidden>Kaydet</button>
                                        <div class="att-head-meta"></div>
                                    </th>
                                @endfor
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </section>
    </main>
@endsection
