<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b1b40">
    <meta name="description" content="Siapegawai, ruang belajar persiapan CPNS independen. Latihan soal contoh dan informasi kelas yang jelas.">
    <title>@yield('title', 'Siapegawai') — Belajar CPNS lebih terarah</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<a class="skip-link" href="#main">Langsung ke konten</a>
<div class="utility"><div class="wrap utility-inner"><span>SIAPEGAWAI <span class="dot">●</span> SIAP JADI PEGAWAI</span><span>Platform belajar independen · tidak terafiliasi dengan BKN</span></div></div>
<header class="header"><div class="wrap header-inner">
    <a href="{{ route('home') }}" aria-label="Beranda Siapegawai"><img class="logo" src="{{ asset('assets/brand/logo-accent.svg') }}" alt="Siapegawai"></a>
    <nav class="nav" aria-label="Navigasi utama">
        <a href="{{ route('home') }}">Beranda</a><a href="{{ route('practice.intro') }}">Latihan SKD</a><a href="{{ route('questions.index') }}">Bank Soal</a><a href="{{ route('page.kelas') }}">Kelas</a><a href="{{ route('page.paket') }}">Paket</a>
    </nav>
    <div class="header-actions">
        @auth
            @if(auth()->user()->isAdmin() && auth()->user()->status === 'active')
                <a class="link-strong" href="{{ route('admin.dashboard') }}">Panel Admin</a>
            @elseif(auth()->user()->status === 'active')
                <a class="link-strong" href="{{ route('dashboard') }}">Dashboard</a>
            @elseif(auth()->user()->role === 'participant')
                <a class="link-strong" href="{{ route('registration.status') }}">Status Akun</a>
            @endif
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="text-button" type="submit">Keluar</button></form>
        @else
            <a class="link-strong" href="{{ route('login') }}">Masuk</a><a class="btn btn-primary btn-small" href="{{ route('register') }}">Daftar</a>
        @endauth
    </div>
</div></header>
<main id="main" class="site-main">
    @if(session('success'))<div class="wrap"><div class="notice success" role="status">{{ session('success') }}</div></div>@endif
    @if($errors->any())<div class="wrap"><div class="notice danger" role="alert"><strong>Mohon periksa formulir.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif
    @yield('content')
</main>
<footer class="footer"><div class="wrap footer-grid">
    <div><img class="logo footer-logo" src="{{ asset('assets/brand/logo-white.svg') }}" alt="Siapegawai"><p>Belajar CPNS, dengan arah yang jelas. Materi contoh tidak mewakili soal resmi BKN.</p></div>
    <div><h3>Jelajahi</h3><a href="{{ route('practice.intro') }}">Latihan gratis</a><a href="{{ route('questions.index') }}">Bank soal</a><a href="{{ route('page.kelas') }}">Kelas</a></div>
    <div><h3>Informasi</h3><a href="{{ route('page.tentang') }}">Tentang</a><a href="{{ route('page.kontak') }}">Kontak</a><a href="{{ route('page.privasi') }}">Privasi</a><a href="{{ route('page.syarat-layanan') }}">Syarat layanan</a></div>
    <div><h3>Catatan</h3><p>Pendaftaran ditinjau manual oleh admin. Tidak ada pembayaran langsung atau jaminan kelulusan.</p></div>
</div><div class="wrap footer-bottom">© {{ date('Y') }} Siapegawai · Layanan edukasi independen</div></footer>
</body></html>
