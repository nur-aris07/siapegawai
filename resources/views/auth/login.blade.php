@extends('layouts.account')
@section('title','Masuk')
@section('content')
<section class="account-panel"><div class="account-heading"><span class="account-kicker">AKSES AKUN</span><h1>Masuk ke Siapegawai</h1><p>Gunakan email dan kata sandi akun. Akun yang menunggu aktivasi akan diarahkan ke halaman status.</p></div>
@if(is_array(session('google_feedback')) && isset(session('google_feedback')['title'], session('google_feedback')['message']))
@php($googleFeedback = session('google_feedback'))
<section class="account-google-feedback" role="alert" aria-labelledby="google-feedback-title">
<span class="account-google-feedback-label">HASIL LOGIN GOOGLE</span>
<h2 id="google-feedback-title">{{ $googleFeedback['title'] }}</h2>
<p>{{ $googleFeedback['message'] }}</p>
@if(($googleFeedback['code'] ?? '') === 'email_exists')
<p><strong>Langkah berikutnya:</strong> Masuk dengan kata sandi akun Siapegawai, buka <strong>Profil saya → Hubungkan Google</strong>, lalu konfirmasi dengan kata sandi sebelum memilih akun Google yang sama.</p>
<div class="account-google-feedback-actions"><a href="#login-form">Masuk dengan kata sandi</a><a href="{{ route('password.request') }}">Lupa kata sandi?</a></div>
@elseif(in_array(($googleFeedback['code'] ?? ''), ['blocked', 'account_not_linkable'], true))
<div class="account-google-feedback-actions"><a href="#login-form">Masuk dengan kata sandi</a><a href="{{ route('page.kontak') }}">Hubungi pengelola</a></div>
@elseif(in_array(($googleFeedback['code'] ?? ''), ['registration_closed', 'signup_conflict'], true))
<div class="account-google-feedback-actions"><a href="#login-form">Masuk dengan kata sandi</a><a href="{{ route('password.request') }}">Lupa kata sandi?</a></div>
@else
<div class="account-google-feedback-actions"><a href="{{ route('google.redirect') }}">Coba Google lagi</a><a href="#login-form">Gunakan kata sandi</a></div>
@endif
</section>
@endif
<form id="login-form" class="form-stack" method="POST" action="{{ route('login.store') }}">@csrf
<label>Email<input type="email" name="email" autocomplete="username" value="{{ old('email') }}" required autofocus></label>
<label>Kata sandi<input type="password" name="password" autocomplete="current-password" required></label>
<button class="button button-primary account-submit" type="submit">Masuk</button>
</form>
@if(app(\App\Services\GoogleIdentityService::class)->enabled())
<div class="account-google-actions">
<div class="account-auth-divider" role="separator" aria-label="Atau">ATAU</div>
<a class="button button-outline account-google-button" href="{{ route('google.redirect') }}">
<svg class="account-google-icon" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
<path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.39 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
<path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.24 5.48-4.74 7.18l7.73 6C44.44 38.03 46.98 31.86 46.98 24.55z"/>
<path fill="#FBBC05" d="M10.53 28.59A14.44 14.44 0 0 1 9.75 24c0-1.59.27-3.13.76-4.59l-7.98-6.19A23.92 23.92 0 0 0 0 24c0 3.87.93 7.51 2.56 10.78l7.97-6.19z"/>
<path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.93-5.8l-7.73-6c-2.15 1.45-4.92 2.3-8.2 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
</svg>
<span>Masuk dengan Google</span>
</a>
</div>
@endif
<div class="account-links"><a href="{{ route('password.request') }}">Lupa kata sandi?</a><a href="{{ route('register') }}">Belum punya akun? Daftar</a></div></section>
@endsection
