@extends('layouts.account')
@section('title','Daftar Akun')
@section('content')
<section class="account-panel account-panel-wide"><div class="account-heading"><span class="account-kicker">AKUN PESERTA</span><h1>Daftar Siapegawai</h1><p>Setelah mendaftar, lanjutkan konfirmasi melalui WhatsApp resmi. Aktivasi dilakukan manual oleh admin.</p></div>
@if(!$registrationOpen)
<div class="account-alert account-alert-danger" role="status"><h2>Registrasi belum dibuka</h2><p>Dokumen privasi dan syarat layanan perlu disahkan serta nomor WhatsApp resmi dikonfigurasi sebelum menerima data pendaftaran.</p><a class="button button-outline" href="{{ route('practice.intro') }}">Coba latihan gratis</a></div>
@else
<form method="POST" action="{{ route('register.store') }}" class="form-stack">@csrf
<label>Nama lengkap<input name="name" autocomplete="name" maxlength="120" value="{{ old('name') }}" required></label>
<label>Email<input type="email" name="email" autocomplete="email" maxlength="255" value="{{ old('email') }}" required></label>
<label>Nomor WhatsApp (08… atau 628…)<input type="tel" name="whatsapp_number" autocomplete="tel" value="{{ old('whatsapp_number') }}" pattern="(0|\+?62)8[0-9]{7,12}" required></label>
<label>Kata sandi (min. 12 karakter, huruf dan angka)<input type="password" name="password" autocomplete="new-password" minlength="12" required></label>
<label>Konfirmasi kata sandi<input type="password" name="password_confirmation" autocomplete="new-password" minlength="12" required></label>
<label class="check-line"><input type="checkbox" name="consent" value="1" required {{ old('consent') ? 'checked' : '' }}><span>Saya menyetujui <a href="{{ route('page.privasi') }}">kebijakan privasi</a> dan <a href="{{ route('page.syarat-layanan') }}">syarat layanan</a>.</span></label>
<button class="button button-primary account-submit" type="submit">Daftar &amp; lanjut WhatsApp</button><p class="small muted">Jangan mengirim NIK, password atau bukti transaksi melalui formulir ini.</p>
</form>
@endif
<div class="account-links"><a href="{{ route('login') }}">Sudah punya akun? Masuk</a></div>
</section>
@endsection
