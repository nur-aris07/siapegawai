@extends('layouts.account')
@section('title','Lengkapi Pendaftaran Google')
@section('content')
<section class="account-panel account-panel-wide">
 <div class="account-heading"><span class="account-kicker">AKUN PESERTA</span><h1>Lengkapi akun Google</h1><p>Akun baru akan langsung aktif GRATIS (Pejuang) setelah kamu menyetujui kebijakan. Tidak ada pembayaran atau konfirmasi WhatsApp admin.</p></div>
 @include('components.form-errors')
 <form method="POST" action="{{ route('google.onboarding.store') }}" class="form-stack">@csrf
 <input type="hidden" name="nonce" value="{{ $profile['nonce'] }}">
 <label>Email terverifikasi Google<input value="{{ $profile['email'] }}" disabled></label>
 <label>Nama lengkap<input name="name" value="{{ old('name', $profile['name'] ?: '') }}" required minlength="2" maxlength="120"></label>
 <label>Nomor WhatsApp peserta<input name="whatsapp_number" type="tel" value="{{ old('whatsapp_number') }}" pattern="(0|\+?62)8[0-9]{7,12}" required></label>
 <label class="check-line"><input name="consent" type="checkbox" value="1" required><span>Saya menyetujui <a href="{{ route('page.privasi') }}">kebijakan privasi</a> dan <a href="{{ route('page.syarat-layanan') }}">syarat layanan</a> (versi {{ config('siapegawai.privacy_version') }}).</span></label>
 <button class="button button-primary account-submit" type="submit">Aktifkan akun GRATIS</button>
 </form>
</section>
@endsection
