@extends('layouts.account')
@section('title','Onboarding Undangan Peserta')
@section('content')
<section class="account-panel account-panel-wide" aria-labelledby="invite-activation-title">
 <div class="account-heading"><span class="account-kicker">UNDANGAN PESERTA</span><h1 id="invite-activation-title">Aktifkan akun GRATIS Anda</h1><p>Undangan dari pengelola tidak memberikan akses belajar sebelum Anda mengatur password dan menyetujui kebijakan.</p></div>
 <dl class="status-details"><dt>Alamat akun</dt><dd>{{ auth()->user()->email }}</dd><dt>Status</dt><dd>{{ auth()->user()->status === 'active' ? 'Aktif' : 'Menunggu onboarding' }}</dd><dt>Password</dt><dd>{{ $passwordSet ? 'Sudah diatur melalui tautan undangan' : 'Belum diatur' }}</dd></dl>
 @if(auth()->user()->status === 'active')
  <p>Akun GRATIS sudah aktif.</p><a class="button button-primary" href="{{ route('dashboard') }}">Buka dashboard</a>
 @elseif(! $passwordSet)
  <p>Periksa email undangan dan gunakan tautan pengaturan password. Jika email tidak diterima, hubungi pengelola untuk pengiriman ulang. Admin tidak pernah mengetahui password Anda.</p>
 @elseif(! $legalReady)
  <p role="alert">Persetujuan kebijakan belum tersedia. Pengelola perlu mengesahkan kebijakan sebelum akun dapat diaktifkan.</p>
 @else
  <form method="POST" action="{{ route('participant.invitation.accept') }}">
   @csrf
   <label class="check-line"><input type="checkbox" name="consent" value="1" required><span>Saya telah membaca dan menyetujui <a href="{{ route('page.privasi') }}" target="_blank" rel="noopener noreferrer">kebijakan privasi</a> dan <a href="{{ route('page.syarat-layanan') }}" target="_blank" rel="noopener noreferrer">syarat layanan</a>.</span></label>
   @error('consent')<p class="field-error" role="alert">{{ $message }}</p>@enderror
   <div class="account-actions"><button class="button button-primary" type="submit">Setujui &amp; aktifkan akun GRATIS</button></div>
  </form>
 @endif
 <form class="account-actions" method="POST" action="{{ route('logout') }}">@csrf <button class="button button-outline" type="submit">Keluar</button></form>
</section>
@endsection
