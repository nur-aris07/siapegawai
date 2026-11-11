@extends('layouts.admin')
@section('title','Quiz Kilat')
@section('content')
<div class="admin-quick-quizzes">
  <div class="admin-v1-heading"><div><p class="admin-v1-crumb">Konten latihan / Quiz Kilat</p><h1>Quiz Kilat</h1>
    <p>Kelola soal dan versi terbit. Pengerjaan peserta belum dibuka sebelum entitlement dan kuota harian tervalidasi.</p></div>
    <a class="button button-primary button-compact" href="{{ route('admin.quick-quizzes.create') }}">Buat quiz</a>
  </div>
  <div class="admin-v1-metrics"><div><strong>{{ $total }}</strong><span>Total quiz</span></div><div><strong>{{ $drafts }}</strong><span>Belum terbit</span></div><div><strong>{{ $published }}</strong><span>Terbit</span></div></div>
  <section class="admin-v1-panel">
    <div class="admin-v1-panel-top"><div><h2>Daftar quiz</h2><p>Daftar ini menggunakan Yajra server-side. Pencarian dan status tidak me-reload halaman.</p></div></div>
    <div class="admin-v1-filters" data-qq-table data-url="{{ route('admin.quick-quizzes.index') }}">
      <label class="admin-v1-field">Cari kode atau judul<input type="search" id="qq-search" autocomplete="off" placeholder="Ketik untuk mencari"></label>
      <label class="admin-v1-field">Status<select id="qq-status"><option value="">Semua status</option><option value="draft">Draft</option><option value="published">Terbit</option><option value="archived">Arsip</option></select></label>
      <button type="button" id="qq-refresh" class="button button-outline button-compact">Muat ulang</button>
    </div>
    <div class="table-scroll" tabindex="0" aria-label="Gulir tabel Quiz Kilat jika diperlukan">
      <table class="data-table admin-v1-table"><thead><tr><th>Kode</th><th>Judul</th><th>Versi terbit</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody id="qq-rows"><tr><td colspan="5">Memuat daftar…</td></tr></tbody></table>
    </div>
    <div class="admin-v1-table-foot"><span id="qq-info" aria-live="polite"></span><div><button type="button" id="qq-prev" class="button button-outline button-compact">Sebelumnya</button><button type="button" id="qq-next" class="button button-outline button-compact">Berikutnya</button></div></div>
    <noscript><p>Pencarian membutuhkan JavaScript. Buka halaman dengan JavaScript aktif untuk menampilkan daftar.</p></noscript>
  </section>
</div>
@endsection
