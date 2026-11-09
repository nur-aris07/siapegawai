@extends('layouts.admin')
@section('title','Template Tryout')
@section('content')
<div class="admin-v1-heading"><div><p class="admin-v1-crumb">Konten / Template tryout</p><h1>Susunan tryout</h1><p>Rakit paket ujian dari versi soal yang sudah diterbitkan. Belum membuka pengerjaan peserta.</p></div><a href="{{ route('tryouts.admin.create') }}" class="button button-primary button-compact">+ Buat tryout</a></div>
<div class="admin-v1-metrics"><div><strong>{{ $total }}</strong><span>Total template</span></div><div><strong>{{ $drafts }}</strong><span>Draft</span></div><div><strong>{{ $published }}</strong><span>Terbit</span></div></div>
<section class="admin-v1-panel"><div class="admin-v1-panel-top"><div><h2>Daftar tryout</h2><p>Publikasi template tidak langsung menyediakan CAT peserta; engine menyusul di S5.</p></div></div>
  <div class="admin-v1-filters" data-tryouts-table data-url="{{ route('tryouts.admin.index') }}">
    <label class="admin-v1-field">Cari tryout<input type="search" id="tryouts-search" placeholder="Kode atau nama" autocomplete="off"></label>
    <label class="admin-v1-field">Status<select id="tryouts-status"><option value="">Semua status</option><option value="draft">Draft</option><option value="published">Terbit</option><option value="archived">Arsip</option></select></label>
    <button type="button" id="tryouts-refresh" class="button button-outline button-compact">Muat ulang</button>
  </div>
  <div class="table-scroll"><table class="data-table admin-v1-table"><thead><tr><th>Kode</th><th>Tryout</th><th>Durasi</th><th>Soal</th><th>Status</th><th>Aksi</th></tr></thead><tbody id="tryouts-rows"><tr><td colspan="6">Memuat…</td></tr></tbody></table></div>
  <div class="admin-v1-table-foot"><span id="tryouts-info" aria-live="polite"></span><div><button type="button" id="tryouts-prev" class="button button-outline button-compact">Sebelumnya</button><button type="button" id="tryouts-next" class="button button-outline button-compact">Berikutnya</button></div></div>
</section>
@endsection
