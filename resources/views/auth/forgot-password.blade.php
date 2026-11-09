@extends('layouts.account')
@section('title','Lupa Kata Sandi')
@section('content')
<section class="account-panel"><div class="account-heading"><span class="account-kicker">PEMULIHAN AKUN</span><h1>Lupa kata sandi</h1><p>Masukkan email akunmu. Instruksi reset akan dikirim apabila alamat terdaftar.</p></div><form class="form-stack" method="POST" action="{{ route('password.email') }}">@csrf<label>Email<input type="email" name="email" autocomplete="email" required></label><button class="button button-primary account-submit" type="submit">Kirim instruksi reset</button></form><p class="small muted account-footnote">Pengiriman email memerlukan pengaturan SMTP yang aktif.</p><a class="text-link account-back" href="{{ route('login') }}">Kembali ke masuk</a></section>
@endsection
