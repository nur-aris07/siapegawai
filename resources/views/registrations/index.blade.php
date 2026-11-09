@extends('layouts.admin')
@section('title','Registrasi Peserta')
@section('content')
<div class="admin-v1-heading"><div><p class="admin-v1-crumb">Peserta / Registrasi</p><h1>Registrasi peserta</h1><p>Cari referensi, verifikasi konfirmasi eksternal, dan catat keputusan aktivasi.</p></div></div>
<section class="admin-v1-panel">
 <div class="admin-v1-panel-top"><div><h2>Daftar registrasi</h2><p>Status dan hasil pencarian langsung dari database.</p></div></div>
 <div class="admin-v1-filters" data-registration-table data-url="{{ route('registration.index') }}"><label class="admin-v1-field" for="registration-search">Referensi, nama, atau email<input type="search" id="registration-search" placeholder="Cari registrasi" autocomplete="off"></label><button class="button button-outline button-compact" type="button" id="registration-refresh">Muat ulang</button></div>
 <div class="table-scroll"><table class="data-table admin-v1-table"><thead><tr><th scope="col">Referensi</th><th scope="col">Nama</th><th scope="col">Email</th><th scope="col">Status</th><th scope="col">Dikirim</th><th scope="col">Aksi</th></tr></thead><tbody id="registration-rows"><tr><td colspan="6">Memuat registrasi…</td></tr></tbody></table></div>
 <div class="admin-v1-table-foot"><span id="registration-info" aria-live="polite"></span><div><button class="button button-outline button-compact" type="button" id="registration-prev">Sebelumnya</button><button class="button button-outline button-compact" type="button" id="registration-next">Berikutnya</button></div></div>
</section>
@endsection
