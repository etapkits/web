@extends('layouts.app')

@section('title', 'Kurumlar · Etakit')

@section('content')
    <x-topbar title="Kurumlar" :user="$user" menu="super" active="organizations" />

    <main class="container-xl py-4">
        <section class="card border-0">
            <div class="card-body p-4">
                <form class="row g-2 align-items-end mb-3" method="get" action="{{ route('super.organizations') }}">
                    <div class="col-md-4">
                        <label class="form-label" for="q">Kurum kodu veya ad</label>
                        <input class="form-control" id="q" name="q" value="{{ $q }}">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-outline-secondary" type="submit">Ara</button>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <a class="btn btn-primary" href="{{ route('super.organizations.create') }}"><i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Kurum ekle</a>
                    </div>
                </form>
                @if (session('status'))
                    <p class="scan-result is-ok small" role="status">{{ session('status') }}</p>
                @endif
                @error('delete')
                    <p class="scan-result small" role="alert">{{ $message }}</p>
                @enderror
                @if ($organizations->isEmpty())
                    <p class="text-secondary mb-0">Kurum yok. Resmi kod ile ekleyin.</p>
                @else
                    <div class="table-responsive">
                        <table class="table teacher-table mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>Kurum</th>
                                    <th>Kod</th>
                                    <th>Durum</th>
                                    <th>Tahta anahtarı</th>
                                    <th>Tahta</th>
                                    <th>Öğretmen</th>
                                    <th>Yönetici</th>
                                    <th class="text-end">İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($organizations as $organization)
                                    <tr @class(['org-inactive' => ! $organization->is_active])>
                                        <td>{{ $organization->name }}</td>
                                        <td class="code">{{ $organization->official_code }}</td>
                                        <td>
                                            @if ($organization->is_active)
                                                <span class="badge badge-open">Aktif</span>
                                            @else
                                                <span class="badge badge-closed">Pasif</span>
                                            @endif
                                        </td>
                                        <td><code class="key-box">{{ $organization->enrollment_key }}</code></td>
                                        <td>{{ $organization->boards_count }}</td>
                                        <td>{{ $organization->teachers_count }}</td>
                                        <td>{{ $organization->users_count }}</td>
                                        <td>
                                            <div class="org-actions">
                                                <a class="btn btn-pastel-main btn-sm" href="{{ route('super.organizations.edit', $organization) }}"><i class="fa-solid fa-pen me-1" aria-hidden="true"></i>Düzenle</a>
                                                <form method="post" action="{{ route('super.organizations.toggle', $organization) }}">
                                                    @csrf
                                                    <button @class(['btn btn-sm', 'btn-pastel-lock' => $organization->is_active, 'btn-pastel-unlock' => ! $organization->is_active]) type="submit">
                                                        @if ($organization->is_active)
                                                            <i class="fa-solid fa-toggle-off me-1" aria-hidden="true"></i>Pasif yap
                                                        @else
                                                            <i class="fa-solid fa-toggle-on me-1" aria-hidden="true"></i>Aktif yap
                                                        @endif
                                                    </button>
                                                </form>
                                                <form class="org-delete" method="post" action="{{ route('super.organizations.destroy', $organization) }}" data-name="{{ $organization->name }}" data-code="{{ $organization->official_code }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="confirm_code" value="">
                                                    <button class="btn btn-pastel-off btn-sm" type="submit"><i class="fa-solid fa-trash me-1" aria-hidden="true"></i>Sil</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>
    </main>
    <script src="{{ asset('js/super-organizations.js') }}?v={{ filemtime(public_path('js/super-organizations.js')) }}"></script>
@endsection
