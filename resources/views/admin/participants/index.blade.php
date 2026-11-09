@extends('layouts.admin')
@section('title','Direktori Peserta')
@section('content')
<div class="admin-v1-heading"><div><p class="admin-v1-crumb">Operasional / Peserta</p><h1>Direktori peserta</h1><p>Lihat status akun dan riwayat aktivitas dasar. Verifikasi pendaftaran tetap melalui menu Registrasi.</p></div></div>
<div class="admin-v1-metrics"><div><strong>{{ $totalParticipants }}</strong><span>Total peserta</span></div><div><strong>{{ $activeParticipants }}</strong><span>Akun aktif</span></div><div><strong>{{ $suspendedParticipants }}</strong><span>Akun ditangguhkan</span></div></div>
<section class="admin-v1-panel">
 <div class="admin-v1-panel-top"><div><h2>Daftar akun</h2><p>Hanya akun peserta. Akun administrator tidak ditampilkan dalam tabel ini.</p></div></div>
 <div class="admin-v1-filters" data-participants-table data-url="{{ route('admin.participants.index') }}">
  <label class="admin-v1-field" for="participants-search">Nama atau email<input id="participants-search" type="search" autocomplete="off" placeholder="Cari peserta"></label>
  <label class="admin-v1-field" for="participants-status">Status<select id="participants-status"><option value="">Semua status</option><option value="pending">Menunggu</option><option value="active">Aktif</option><option value="suspended">Ditangguhkan</option><option value="rejected">Ditolak</option></select></label>
  <button class="button button-outline button-compact" id="participants-refresh" type="button">Muat ulang</button>
 </div>
 <div class="table-scroll"><table class="data-table admin-v1-table"><thead><tr><th scope="col">Nama</th><th scope="col">Email</th><th scope="col">Status akun</th><th scope="col">Dibuat</th><th scope="col">Aksi</th></tr></thead><tbody id="participants-rows"><tr><td colspan="5">Memuat akun peserta…</td></tr></tbody></table></div>
 <div class="admin-v1-table-foot"><span id="participants-info" aria-live="polite"></span><div><button class="button button-outline button-compact" type="button" id="participants-prev">Sebelumnya</button><button class="button button-outline button-compact" type="button" id="participants-next">Berikutnya</button></div></div>
</section>
@endsection
