<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0B1B40">
    <meta name="description" content="Siapegawai, ruang belajar persiapan CPNS independen. Latihan SKD dan pembahasan soal contoh.">
    <title>@yield('title', 'Siapegawai') — Siapegawai</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="shell-public">
<a class="skip-link" href="#main">Langsung ke konten</a>
<div class="utility"><div class="container utility-inner"><span>SIAPEGAWAI <span class="utility-dot"></span> SIAP JADI PEGAWAI</span><span>Layanan belajar CPNS independen · tidak terafiliasi dengan BKN</span></div></div>
<header class="site-header"><div class="container header-inner">
    <a class="brand" href="{{ route('home') }}" aria-label="Siapegawai, kembali ke beranda"><img src="{{ asset('assets/brand/logo-accent.svg') }}" alt="Siapegawai" width="184" height="44"></a>
    <nav class="site-nav" id="main-nav" aria-label="Navigasi utama">
        <a href="{{ route('home') }}" @class(['active'=>request()->routeIs('home')]) @if(request()->routeIs('home')) aria-current="page" @endif>Beranda</a>
        <a href="{{ route('practice.intro') }}" @class(['active'=>request()->routeIs('practice.*')]) @if(request()->routeIs('practice.*')) aria-current="page" @endif>Latihan SKD</a>
        <a href="{{ route('questions.index') }}" @class(['active'=>request()->routeIs('questions.*')]) @if(request()->routeIs('questions.*')) aria-current="page" @endif>Bank soal</a>
        <a href="{{ route('page.kelas') }}" @class(['active'=>request()->routeIs('page.kelas')]) @if(request()->routeIs('page.kelas')) aria-current="page" @endif>Kelas</a>
        <a href="{{ route('page.paket') }}" @class(['active'=>request()->routeIs('page.paket')]) @if(request()->routeIs('page.paket')) aria-current="page" @endif>Paket</a>
    </nav>
    <div class="header-right">
        @auth
            @if(auth()->user()->isAdmin())
                <a class="progress-link" href="{{ route('admin.dashboard') }}">Panel admin</a>
            @elseif(auth()->user()->status === 'active')
                <a class="progress-link" href="{{ route('dashboard') }}">Dashboard peserta</a>
            @else
                <a class="progress-link" href="{{ route('registration.status') }}">Status aktivasi</a>
            @endif
        @else
            <a class="progress-link" href="{{ route('login') }}">Masuk</a>
        @endauth
        <button class="menu-toggle" id="menu-toggle" type="button" aria-label="Buka menu navigasi" aria-controls="main-nav" aria-expanded="false"><svg class="icon" aria-hidden="true"><use href="#ic-menu-2"></use></svg></button>
    </div>
</div></header>
<main id="main" tabindex="-1">@yield('content')</main>
<footer class="site-footer"><div class="container footer-main">
    <div class="footer-about"><img src="{{ asset('assets/brand/logo-white.svg') }}" alt="Siapegawai" width="178" height="42"><p>Ruang belajar untuk mempersiapkan seleksi CPNS dengan langkah yang lebih jelas.</p></div>
    <div class="footer-links"><span class="footer-label">Jelajahi</span><a href="{{ route('practice.intro') }}">Latihan SKD</a><a href="{{ route('questions.index') }}">Bank soal</a><a href="{{ route('page.kelas') }}">Rencana kelas</a></div>
    <div class="footer-links"><span class="footer-label">Informasi</span><a href="{{ route('page.paket') }}">Rancangan paket</a><a href="{{ route('page.privasi') }}">Privasi & penggunaan</a><a href="{{ route('page.syarat-layanan') }}">Syarat layanan</a></div>
    <div class="footer-note"><span class="footer-label">Catatan</span><p>Materi dan bobot penilaian merupakan contoh internal, bukan simulasi resmi BKN. Konfirmasi akun dilakukan manual oleh admin.</p></div>
</div><div class="container footer-bottom"><span>© {{ date('Y') }} Siapegawai · Edukasi independen</span><span>Dibuat untuk calon peserta, untuk langkah belajar yang nyata.</span></div></footer>
@include('components.icon-sprite')
@include('components.toast-region')
</body>
</html>
