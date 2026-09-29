@extends('layouts.app')

@include('partials.datatables')

@push('scripts')
    <script src="{{ asset('js/students.js') }}?v={{ filemtime(public_path('js/students.js')) }}"></script>
@endpush

@section('title', 'Öğrenciler · Etakit')

@section('content')
    <x-topbar title="Öğrenciler" :organization="$organization" :user="$user" menu="panel" active="students" />

    <main
        id="students"
        class="container-xl py-4"
        data-data-url="{{ route('panel.students.data') }}"
        data-store-url="{{ route('panel.students.store') }}"
        data-import-url="{{ route('panel.students.import') }}"
    >
        <section class="card border-0">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                    <div>
                        <h1 class="h4 mb-1">Öğrenci listesi</h1>
                        <p class="text-secondary small mb-0">Ad, soyad, numara, sınıf veya veli telefonu ile arayabilirsiniz.</p>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <select id="class-filter" class="form-select form-select-sm dt-filter" aria-label="Sınıf filtresi">
                            <option value="">Tüm sınıflar</option>
                            @foreach ($classNames as $className)
                                <option value="{{ $className }}">{{ $className }}</option>
                            @endforeach
                        </select>
                        <button id="student-import" class="btn btn-outline-secondary btn-sm" type="button">Excel'den aktar</button>
                        <a id="student-export" class="btn btn-outline-secondary btn-sm" href="{{ route('panel.students.export') }}" data-base-url="{{ route('panel.students.export') }}">Excel'e aktar</a>
                        <button id="student-add" class="btn btn-primary btn-sm" type="button">Öğrenci ekle</button>
                    </div>
                </div>

                <p id="student-status" class="scan-result small" role="status"></p>

                <div class="table-responsive">
                    <table id="student-table" class="table teacher-table align-middle w-100">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Ad</th>
                                <th>Soyad</th>
                                <th>Sınıf</th>
                                <th>Veli telefonu</th>
                                <th class="text-end">İşlem</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </section>
    </main>

    <div class="modal fade" id="student-modal" tabindex="-1" aria-labelledby="student-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form id="student-form" class="modal-content" novalidate>
                <div class="modal-header">
                    <h2 class="modal-title h5" id="student-modal-title">Öğrenci ekle</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="student_no">Öğrenci no</label>
                            <input class="form-control" id="student_no" name="student_no" required maxlength="20" autocomplete="off">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="class_name">Sınıf</label>
                            <input class="form-control" id="class_name" name="class_name" required maxlength="20" list="class-options" placeholder="9-A" autocomplete="off">
                            <datalist id="class-options">
                                @foreach ($classNames as $className)
                                    <option value="{{ $className }}"></option>
                                @endforeach
                            </datalist>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="first_name">Ad</label>
                            <input class="form-control" id="first_name" name="first_name" required maxlength="100" autocomplete="off">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="last_name">Soyad</label>
                            <input class="form-control" id="last_name" name="last_name" required maxlength="100" autocomplete="off">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="parent_phone">Veli telefonu <span class="text-secondary small">(isteğe bağlı)</span></label>
                            <input class="form-control" id="parent_phone" name="parent_phone" type="tel" maxlength="20" placeholder="05xx xxx xx xx" autocomplete="off">
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                    <p id="student-form-error" class="form-error small mb-0" role="alert"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Vazgeç</button>
                    <button type="submit" class="btn btn-primary" id="student-save">Kaydet</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="import-modal" tabindex="-1" aria-labelledby="import-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form id="import-form" class="modal-content" enctype="multipart/form-data" novalidate>
                <div class="modal-header">
                    <h2 class="modal-title h5" id="import-modal-title">Excel'den öğrenci aktar</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <p class="text-secondary small">
                        e-Okul &rsaquo; Sınıf Listesi raporunu Excel olarak indirip yükleyin. Tüm şubeler tek dosyada olabilir.
                        Öğrenciler numaraya göre eşleşir: yeni numaralar eklenir, kayıtlı olanların ad, soyad ve sınıfı güncellenir.
                        Veli telefonları korunur.
                    </p>
                    <div class="mb-3">
                        <label class="form-label" for="import-file">Dosya</label>
                        <input class="form-control" id="import-file" name="file" type="file" accept=".xls,.xlsx,.csv" required>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="import-remove" name="remove_missing">
                        <label class="form-check-label" for="import-remove">Dosyada olmayan öğrencileri sil (mezun, nakil)</label>
                        <div class="invalid-feedback"></div>
                    </div>
                    <p id="import-error" class="form-error small mb-0" role="alert"></p>
                    <div id="import-result" class="mt-3" hidden>
                        <p id="import-summary" class="scan-result is-ok small mb-2"></p>
                        <div id="import-warnings" class="import-warnings small" hidden>
                            <p class="fw-semibold mb-1">Uyarılar</p>
                            <ul class="mb-0"></ul>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Kapat</button>
                    <button type="submit" class="btn btn-primary" id="import-save">Aktar</button>
                </div>
            </form>
        </div>
    </div>
@endsection
