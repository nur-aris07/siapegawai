@extends('layouts.workspace')
@section('title','Profil saya')
@section('content')
<div class="workspace-heading"><div><span class="workspace-label">AKUN PESERTA</span><h1>Profil saya</h1><p>Perbarui informasi dasar dan kata sandi tanpa mengubah status pendaftaran.</p></div></div>
@include('components.form-errors')
<div class="workspace-lines">
<section class="workspace-line">
<div><h2>Informasi akun</h2><p>Email tidak dapat diubah melalui halaman ini karena digunakan untuk login dan verifikasi.</p>
<form action="{{ route('member.profile.update') }}" method="POST" class="s7-form">@csrf @method('PATCH')
<label>Nama lengkap<input name="name" type="text" required maxlength="120" value="{{ old('name',$member->name) }}" autocomplete="name"></label>
<label>Email akun<input type="email" value="{{ $member->email }}" disabled aria-describedby="email-help"></label><p id="email-help" class="member-disclaimer">Perubahan email memerlukan proses verifikasi terpisah.</p>
<label>WhatsApp (opsional, format 628... atau +628...)<input name="whatsapp_number" type="tel" maxlength="24" autocomplete="tel" value="{{ old('whatsapp_number',$member->whatsapp_number) }}"></label>
<button class="button button-primary" type="submit">Simpan profil</button>
</form></div></section>
<section class="workspace-line"><div><h2>Ganti kata sandi</h2><p>Kata sandi baru minimal 12 karakter serta mengandung huruf dan angka. Kamu akan diminta login kembali.</p>
<form action="{{ route('member.profile.password') }}" method="POST" class="s7-form">@csrf
<label>Kata sandi sekarang<input name="current_password" type="password" autocomplete="current-password" required maxlength="200"></label>
<label>Kata sandi baru<input name="password" type="password" autocomplete="new-password" minlength="12" required maxlength="200"></label>
<label>Konfirmasi kata sandi baru<input name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required maxlength="200"></label>
<button class="button button-outline" type="submit">Ubah kata sandi dan login ulang</button>
</form></div></section>
</div>
@endsection
