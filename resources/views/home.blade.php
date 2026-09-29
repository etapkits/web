@extends('layouts.app')

@section('title', 'Etakit · Etkileşimli tahta kilidi')
@section('robots', 'index,follow')

@push('head')
    <meta name="description" content="Okullar için etkileşimli tahta kilitleme ve yönetme hizmeti. Öğretmenler telefon doğrulaması ile giriş yapar; idare tahtaları, öğretmenleri ve kilit ayarlarını yönetir.">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
@endpush

@section('content')
    <nav class="home-corner" aria-label="Diğer girişler">
        <a class="home-corner-link" href="{{ route('login') }}" title="Kurum yöneticisi girişi">
            <i class="fa-solid fa-school" aria-hidden="true"></i>
            <span>Kurum</span>
        </a>
        <a class="home-corner-link" href="{{ route('super.login') }}" title="Süper yönetici girişi">
            <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
            <span>Admin</span>
        </a>
    </nav>

    <main class="login-screen d-flex flex-column align-items-center justify-content-center px-3 py-5">
        <div class="home-intro text-center mb-4">
            <h2 class="h4 mb-2">Etkileşimli tahta kilidi</h2>
            <p class="text-secondary mb-0">Okullar için etkileşimli tahta kilitleme ve yönetme hizmeti.</p>
        </div>

        @include('partials.teacher-login-form')
    </main>
@endsection
