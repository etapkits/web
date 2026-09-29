@extends('layouts.app')

@section('title', 'Çıkış · Etakit')

@php
    [$loginUrl, $loginLabel] = match (request()->query('giris')) {
        'panel' => [route('login'), 'Kurum girişi'],
        'super' => [route('super.login'), 'Admin girişi'],
        default => [null, null],
    };
@endphp

@section('content')
    <main class="login-screen d-flex align-items-center justify-content-center px-3 py-5">
        <section class="card login-card border-0 text-center">
            <div class="card-body p-4 p-md-5">
                <p class="eyebrow mb-2">Etakit</p>
                <h1 @class(['h4', 'mb-4' => $loginUrl, 'mb-0' => ! $loginUrl])>Çıkışı tamamlamak için pencereyi kapatınız</h1>
                @if ($loginUrl)
                    <a class="btn btn-outline-secondary" href="{{ $loginUrl }}">{{ $loginLabel }}</a>
                @endif
            </div>
        </section>
    </main>
@endsection
