@extends('layouts.admin')
@section('title','Keamanan akun')
@section('content')
<div class="admin-v1-heading">
  <div>
    <p class="admin-v1-crumb">Akun / Keamanan</p>
    <h1>Keamanan akun</h1>
    <p>Ubah kata sandi administrator dengan verifikasi kata sandi saat ini.</p>
  </div>
</div>
<section class="admin-v1-panel admin-v1-form-panel">
  <h2>Ganti kata sandi</h2>
  <p>Setelah berhasil, semua sesi administrator lama akan ditolak saat mencoba mengakses panel. Kamu juga perlu login kembali di perangkat ini.</p>
  <form method="POST" action="{{ route('admin.security.password') }}" class="admin-v1-action-form" autocomplete="off">
    @csrf
    <label class="admin-v1-field" for="current-password">Kata sandi saat ini
      <input id="current-password" name="current_password" type="password" autocomplete="current-password" required maxlength="200">
    </label>
    <label class="admin-v1-field" for="new-password">Kata sandi baru (minimal 12 karakter, mengandung huruf dan angka)
      <input id="new-password" name="password" type="password" autocomplete="new-password" required minlength="12" maxlength="200">
    </label>
    <label class="admin-v1-field" for="confirm-password">Konfirmasi kata sandi baru
      <input id="confirm-password" name="password_confirmation" type="password" autocomplete="new-password" required minlength="12" maxlength="200">
    </label>
    <button class="button button-primary" type="submit">Ganti kata sandi dan akhiri sesi</button>
  </form>
</section>
@endsection
