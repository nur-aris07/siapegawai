@extends('layouts.admin')
@section('title','Tambah Peserta')
@section('content')
<div class="admin-v1-heading"><div><p class="admin-v1-crumb"><a href="{{ route('admin.participants.index') }}">Direktori peserta</a> / Tambah peserta</p><h1>Tambah peserta</h1><p>Undang peserta dengan akun GRATIS. Peserta menetapkan password dan menyetujui kebijakan sendiri.</p></div></div>
<section class="admin-v1-panel" aria-labelledby="invite-participant-title">
 <h2 id="invite-participant-title">Informasi peserta</h2>
 <p>Hanya Superadmin yang dapat membuat undangan. Akun dibuat dalam status <strong>menunggu onboarding</strong>, tanpa akses premium.</p>
 <form method="POST" action="{{ route('admin.participants.store') }}" class="admin-v1-action-form">
  @csrf
  <label class="admin-v1-field" for="participant-invite-name">Nama lengkap
   <input id="participant-invite-name" name="name" required minlength="2" maxlength="120" autocomplete="name" value="{{ old('name') }}">
  </label>
  @error('name')<p class="field-error" role="alert">{{ $message }}</p>@enderror
  <label class="admin-v1-field" for="participant-invite-email">Email peserta
   <input id="participant-invite-email" name="email" type="email" required maxlength="255" autocomplete="off" value="{{ old('email') }}">
  </label>
  @error('email')<p class="field-error" role="alert">{{ $message }}</p>@enderror
  <label class="admin-v1-field" for="participant-invite-wa">Nomor WhatsApp peserta (opsional)
   <input id="participant-invite-wa" name="whatsapp_number" inputmode="tel" maxlength="24" autocomplete="off" value="{{ old('whatsapp_number') }}" placeholder="08… atau 628…">
  </label>
  @error('whatsapp_number')<p class="field-error" role="alert">{{ $message }}</p>@enderror
  <p class="admin-v1-muted">Email dikirimi tautan sekali pakai melalui fitur pemulihan password. Password dan tautan rahasia tidak ditampilkan kepada admin. Kesediaan pengiriman email harus diperiksa pada lingkungan operasional.</p>
  <div class="account-actions"><button class="button button-primary" type="submit">Buat akun &amp; kirim undangan</button> <a class="button button-outline" href="{{ route('admin.participants.index') }}">Batal</a></div>
 </form>
</section>
@endsection
