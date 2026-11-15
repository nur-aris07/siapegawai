@extends('layouts.account')
@section('title','Daftar Akun')
@section('content')
<section class="account-panel account-panel-wide"><div class="account-heading"><span class="account-kicker">AKUN PESERTA</span><h1>Daftar Siapegawai</h1><p>Daftar GRATIS dan akun peserta langsung aktif. Akses belajar tetap mengikuti kebijakan paket dasar yang tersedia.</p></div>
@if(!$registrationOpen)
<div class="account-alert account-alert-danger" role="status"><h2>Registrasi belum dibuka</h2><p>Pendaftaran hanya dibuka setelah kebijakan privasi dan syarat layanan resmi disetujui pengelola.</p><a class="button button-outline" href="{{ route('practice.intro') }}">Coba latihan gratis</a></div>
@else
<form method="POST" action="{{ route('register.store') }}" class="form-stack">@csrf
<label>Nama lengkap<input name="name" autocomplete="name" maxlength="120" value="{{ old('name') }}" required></label>
<label>Email<input type="email" name="email" autocomplete="email" maxlength="255" value="{{ old('email') }}" required></label>
<label>Nomor WhatsApp (08… atau 628…)<input type="tel" name="whatsapp_number" autocomplete="tel" value="{{ old('whatsapp_number') }}" pattern="(0|\+?62)8[0-9]{7,12}" required></label>
<label>Kata sandi (min. 12 karakter, huruf dan angka)<input type="password" name="password" autocomplete="new-password" minlength="12" required></label>
<label>Konfirmasi kata sandi<input type="password" name="password_confirmation" autocomplete="new-password" minlength="12" required></label>
<label class="check-line"><input type="checkbox" name="consent" value="1" required {{ old('consent') ? 'checked' : '' }}><span>Saya menyetujui <a href="{{ route('page.privasi') }}">kebijakan privasi</a> dan <a href="{{ route('page.syarat-layanan') }}">syarat layanan</a>.</span></label>
<button class="button button-primary account-submit" type="submit">Daftar GRATIS</button><p class="small muted">Pendaftaran GRATIS tidak membutuhkan pembayaran atau persetujuan lewat WhatsApp. Jangan mengirim NIK atau bukti transaksi pada formulir ini.</p>
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
<span>Daftar dengan Google</span>
</a>
<p class="account-google-note">Akun Google baru tetap harus melengkapi WhatsApp peserta dan menyetujui kebijakan.</p>
</div>
@endif
@endif
<div class="account-links"><a href="{{ route('login') }}">Sudah punya akun? Masuk</a></div>
</section>
@endsection
