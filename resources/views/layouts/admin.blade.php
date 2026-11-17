<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0B1B40">
<title>@yield('title','Administrasi') — Siapegawai</title>
@vite(['resources/css/app.css', 'resources/css/admin-v1.css', 'resources/js/app.js', 'resources/js/admin-navigation.js', 'resources/js/admin-questions.js', 'resources/js/admin-tryouts.js', 'resources/js/admin-participants.js', 'resources/js/admin-quick-quizzes.js'])
@if(request()->routeIs('admin.site-settings.*'))@vite(['resources/js/admin-site-settings.js'])@endif
@if(request()->routeIs('admin.packages.*'))@vite(['resources/css/pkg-catalog.css', 'resources/js/admin-package-catalog.js'])@endif
@if(request()->routeIs('questions.import.*'))@vite(['resources/css/admin-question-import.css','resources/js/admin-question-import.js'])@endif
@if(request()->routeIs('tryouts.admin.create','tryouts.admin.edit','admin.quick-quizzes.create','admin.quick-quizzes.edit','admin.class-practices.create','admin.class-practices.edit'))@vite(['resources/css/admin-bulk-assignment.css','resources/js/admin-bulk-assignment.js'])@endif
</head>
<body class="shell-workspace shell-admin admin-v1">
<a class="skip-link" href="#main">Langsung ke konten</a>
<header class="admin-header admin-v1-header">
  <div class="admin-v1-header-inner">
    <button class="admin-v1-mobile-menu" type="button" id="admin-menu-toggle" aria-controls="admin-primary-navigation" aria-expanded="false" aria-label="Buka navigasi admin"><svg class="icon" aria-hidden="true"><use href="#ic-menu-2"></use></svg></button>
    <a class="admin-v1-brand" href="{{ route('admin.dashboard') }}" aria-label="Beranda administrasi Siapegawai"><img src="{{ asset('assets/brand/logo-accent.svg') }}" width="163" height="38" alt="Siapegawai"></a>
    <span class="admin-v1-app-name">Workspace Admin</span>
    <div class="admin-v1-profile"><span class="admin-v1-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span><span class="admin-v1-identity"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->role === 'superadmin' ? 'Superadmin' : 'Admin Operasional' }}</small></span></div>
    <form class="admin-v1-header-logout" method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="button button-outline button-compact">Keluar</button></form>
  </div>
</header>
<div class="admin-frame admin-v1-frame">
  <div class="admin-nav-overlay" id="admin-nav-overlay" hidden aria-hidden="true"></div>
  <aside id="admin-primary-navigation" class="admin-sidebar admin-v1-sidebar" aria-label="Navigasi administrasi">
    <div class="admin-nav-toolbar">
      <span class="admin-nav-toolbar-title">Navigasi</span>
      <button class="admin-nav-collapse-toggle" type="button" id="admin-nav-collapse" aria-controls="admin-primary-navigation" aria-expanded="true" aria-label="Ciutkan sidebar" title="Ciutkan sidebar">
        <svg class="icon" aria-hidden="true"><use href="#ic-chevron-left"></use></svg>
      </button>
      <button class="admin-nav-close" type="button" id="admin-nav-close" aria-label="Tutup navigasi">
        <svg class="icon" aria-hidden="true"><use href="#ic-x"></use></svg>
      </button>
    </div>
    @include('admin.partials.navigation')
    <a class="admin-v1-public-link" href="{{ route('home') }}"><svg class="icon" aria-hidden="true"><use href="#ic-arrow-up-right"></use></svg><span class="admin-nav-label">Lihat website publik</span></a>
    <section class="admin-nav-account" aria-label="Akun admin yang sedang masuk">
      <p class="admin-nav-account-heading">Akun masuk</p>
      <div class="admin-nav-account-user">
        <span class="admin-v1-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
        <span class="admin-nav-account-identity">
          <strong>{{ auth()->user()->name }}</strong>
          <small>{{ auth()->user()->role === 'superadmin' ? 'Superadmin' : 'Admin Operasional' }}</small>
        </span>
      </div>
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button class="admin-nav-account-logout" type="submit">Keluar akun</button>
      </form>
    </section>
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
