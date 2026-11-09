<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#0B1B40">
    <title>@yield('title','Akun') — Siapegawai</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="shell-account">
<a class="skip-link" href="#main">Langsung ke konten</a>
<header class="account-header"><div class="container account-header-inner"><a href="{{ route('home') }}" aria-label="Kembali ke beranda"><img src="{{ asset('assets/brand/logo-accent.svg') }}" alt="Siapegawai" width="176" height="42"></a><a class="text-link" href="{{ route('home') }}"><svg class="icon icon-small" aria-hidden="true"><use href="#ic-chevron-left"></use></svg> Kembali ke website</a></div></header>
<main id="main" class="account-main" tabindex="-1">@include('components.form-errors') @yield('content')</main>
<footer class="account-footer"><div class="container account-footer-inner"><span>© {{ date('Y') }} Siapegawai · Layanan independen</span><div><a href="{{ route('page.privasi') }}">Privasi</a><a href="{{ route('page.syarat-layanan') }}">Syarat layanan</a><a href="{{ route('page.kontak') }}">Bantuan</a></div></div></footer>
@include('components.icon-sprite')
@include('components.toast-region')
</body></html>
