<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0B1B40">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title','Area Peserta') — Siapegawai</title>
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/css/member-cat.css', 'resources/js/member-cat.js', 'resources/css/s7-member.css', 'resources/js/member-workspace-nav.js'])
</head>
<body class="shell-workspace shell-participant">
<a class="skip-link" href="#main">Langsung ke konten</a>
<header class="workspace-header">
    <div class="container workspace-header-inner">
        <a class="workspace-brand" href="{{ route('dashboard') }}" aria-label="Siapegawai — ringkasan peserta">
            <img src="{{ asset('assets/brand/logo-accent.svg') }}" alt="Siapegawai" width="166" height="40">
        </a>
        <div class="workspace-identity">
            <span class="workspace-label">AREA PESERTA</span>
            <span>{{ auth()->user()->name }}</span>
        </div>
        <button class="workspace-menu-toggle" type="button" aria-controls="participant-workspace-nav" aria-expanded="false" data-workspace-menu-toggle>
            <span aria-hidden="true" class="workspace-menu-symbol">☰</span><span>Menu</span>
        </button>
        <nav id="participant-workspace-nav" aria-label="Navigasi utama peserta" class="workspace-nav" data-workspace-nav>
            <a href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif>Ringkasan</a>
            <a href="{{ route('member.tryouts.index') }}" @if(request()->routeIs('member.tryouts.*')) aria-current="page" @endif>Tryout saya</a>
            <a href="{{ route('member.classes.index') }}" @if(request()->routeIs('member.classes.*')) aria-current="page" @endif>Kelas saya</a>
            <a href="{{ route('member.quick-quizzes.index') }}" @if(request()->routeIs('member.quick-quizzes.*')) aria-current="page" @endif>Quiz Kilat</a>
            <a href="{{ route('member.analytics.index') }}" @if(request()->routeIs('member.analytics.index')) aria-current="page" @endif>Analisis kemampuan</a>
        </nav>
        <details class="workspace-account" data-workspace-account>
            <summary aria-label="Buka menu akun peserta">
                <span class="workspace-account-name">{{ auth()->user()->name }}</span>
                <span class="workspace-account-label">Akun</span>
                <span class="workspace-account-chevron" aria-hidden="true"></span>
            </summary>
            <div class="workspace-account-panel">
                <a href="{{ route('member.profile.edit') }}" @if(request()->routeIs('member.profile.*')) aria-current="page" @endif>Profil saya</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit">Keluar</button>
                </form>
            </div>
        </details>
    </div>
</header>
<main id="main" class="workspace-main" tabindex="-1"><div class="container">@yield('content')</div></main>
@include('components.icon-sprite')
@include('components.toast-region')
</body>
</html>
