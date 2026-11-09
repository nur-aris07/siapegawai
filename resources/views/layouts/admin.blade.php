<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0B1B40">
<title>@yield('title','Administrasi') — Siapegawai</title>
@vite(['resources/css/app.css', 'resources/css/admin-v1.css', 'resources/js/app.js', 'resources/js/admin-questions.js', 'resources/js/admin-tryouts.js', 'resources/js/admin-participants.js'])
</head>
<body class="shell-workspace shell-admin admin-v1">
<a class="skip-link" href="#main">Langsung ke konten</a>
<header class="admin-header admin-v1-header">
  <div class="admin-v1-header-inner">
    <button class="admin-v1-mobile-menu" type="button" id="admin-menu-toggle" aria-controls="admin-primary-navigation" aria-expanded="false" aria-label="Buka navigasi admin"><svg class="icon" aria-hidden="true"><use href="#ic-menu-2"></use></svg></button>
    <a class="admin-v1-brand" href="{{ route('admin.dashboard') }}" aria-label="Beranda administrasi Siapegawai"><img src="{{ asset('assets/brand/logo-accent.svg') }}" width="163" height="38" alt="Siapegawai"></a>
    <span class="admin-v1-app-name">Workspace Admin</span>
    <div class="admin-v1-profile"><span class="admin-v1-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span><span class="admin-v1-identity"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->role === 'superadmin' ? 'Superadmin' : 'Admin Operasional' }}</small></span></div>
    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="button button-outline button-compact">Keluar</button></form>
  </div>
</header>
<div class="admin-frame admin-v1-frame">
  <aside id="admin-primary-navigation" class="admin-sidebar admin-v1-sidebar" aria-label="Navigasi administrasi">
    <nav aria-label="Menu utama">
      <span class="admin-v1-nav-section">OPERASIONAL</span>
      <a href="{{ route('admin.dashboard') }}" @class(['current'=>request()->routeIs('admin.dashboard')]) @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif><svg class="icon" aria-hidden="true"><use href="#ic-chart-bar"></use></svg><span>Ringkasan</span></a>
      <a href="{{ route('registration.index') }}" @class(['current'=>request()->routeIs('registration.*')]) @if(request()->routeIs('registration.*')) aria-current="page" @endif><svg class="icon" aria-hidden="true"><use href="#ic-users"></use></svg><span>Registrasi peserta</span></a>
      <a href="{{ route('admin.participants.index') }}" @class(['current'=>request()->routeIs('admin.participants.*')]) @if(request()->routeIs('admin.participants.*')) aria-current="page" @endif><svg class="icon" aria-hidden="true"><use href="#ic-users"></use></svg><span>Direktori peserta</span></a>
      <span class="admin-v1-nav-section">KONTEN LATIHAN</span>
      <a href="{{ route('questions.admin.index') }}" @class(['current'=>request()->routeIs('questions.admin.*')]) @if(request()->routeIs('questions.admin.*')) aria-current="page" @endif><svg class="icon" aria-hidden="true"><use href="#ic-book"></use></svg><span>Bank soal</span></a>
      <a href="{{ route('subjects.admin.index') }}" @class(['current'=>request()->routeIs('subjects.admin.*')]) @if(request()->routeIs('subjects.admin.*')) aria-current="page" @endif><svg class="icon" aria-hidden="true"><use href="#ic-book"></use></svg><span>Kategori & topik</span></a>
      <a href="{{ route('tryouts.admin.index') }}" @class(['current'=>request()->routeIs('tryouts.admin.*')]) @if(request()->routeIs('tryouts.admin.*')) aria-current="page" @endif><svg class="icon" aria-hidden="true"><use href="#ic-clipboard"></use></svg><span>Template tryout</span></a>
      <a href="{{ route('admin.classes.index') }}" @class(['current'=>request()->routeIs('admin.classes.*')]) @if(request()->routeIs('admin.classes.*')) aria-current="page" @endif><svg class="icon" aria-hidden="true"><use href="#ic-book"></use></svg><span>Kelas &amp; materi</span></a>
      <span class="admin-v1-nav-section">AKUN</span>
      <a href="{{ route('admin.security.edit') }}" @class(['current'=>request()->routeIs('admin.security.*')]) @if(request()->routeIs('admin.security.*')) aria-current="page" @endif><svg class="icon" aria-hidden="true"><use href="#ic-users"></use></svg><span>Keamanan akun</span></a>
      <span class="admin-v1-nav-section">TAHAP BERIKUTNYA</span>
      <span class="admin-v1-nav-unavailable"><svg class="icon" aria-hidden="true"><use href="#ic-clipboard"></use></svg><span>CAT peserta</span><small>Aktif untuk peserta</small></span>
      <span class="admin-v1-nav-unavailable"><svg class="icon" aria-hidden="true"><use href="#ic-book"></use></svg><span>Paket berbayar</span><small>Belum aktif</small></span>
    </nav>
    <a class="admin-v1-public-link" href="{{ route('home') }}"><svg class="icon" aria-hidden="true"><use href="#ic-arrow-up-right"></use></svg> Lihat website publik</a>
  </aside>
  <main id="main" class="admin-main admin-v1-main" tabindex="-1">
    @include('components.form-errors')
    @yield('content')
  </main>
</div>
@include('components.icon-sprite')
@include('components.toast-region')
</body>
</html>
