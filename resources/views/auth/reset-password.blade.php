@extends('layouts.account')
@section('title','Reset Kata Sandi')
@section('content')
<section class="account-panel"><div class="account-heading"><span class="account-kicker">PEMULIHAN AKUN</span><h1>Buat kata sandi baru</h1><p>Gunakan kata sandi yang belum pernah dipakai di akun lain.</p></div><form class="form-stack" method="POST" action="{{ route('password.update') }}">@csrf<input type="hidden" name="token" value="{{ $token }}"><label>Email<input type="email" name="email" value="{{ old('email',$email) }}" required></label><label>Kata sandi baru<input type="password" name="password" minlength="12" autocomplete="new-password" required></label><label>Konfirmasi kata sandi<input type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required></label><button type="submit" class="button button-primary account-submit">Simpan kata sandi baru</button></form></section>
@endsection
