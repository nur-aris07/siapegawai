@extends('layouts.account')
@section('title','Masuk')
@section('content')
<section class="account-panel"><div class="account-heading"><span class="account-kicker">AKSES AKUN</span><h1>Masuk ke Siapegawai</h1><p>Gunakan email dan kata sandi akun. Akun yang menunggu aktivasi akan diarahkan ke halaman status.</p></div><form class="form-stack" method="POST" action="{{ route('login.store') }}">@csrf
<label>Email<input type="email" name="email" autocomplete="username" value="{{ old('email') }}" required autofocus></label>
<label>Kata sandi<input type="password" name="password" autocomplete="current-password" required></label>
<button class="button button-primary account-submit" type="submit">Masuk</button>
</form><div class="account-links"><a href="{{ route('password.request') }}">Lupa kata sandi?</a><a href="{{ route('register') }}">Belum punya akun? Daftar</a></div></section>
@endsection
