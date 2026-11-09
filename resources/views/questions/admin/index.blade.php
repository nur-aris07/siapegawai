@extends('layouts.admin')
@section('title', 'Bank Soal Produksi')
@section('content')
<div class="admin-v1-heading">
  <div><p class="admin-v1-crumb">Konten / Bank soal</p><h1>Bank soal produksi</h1><p>Kelola draft, pembahasan, dan versi soal yang dapat diterbitkan.</p></div>
  <a href="{{ route('questions.admin.create') }}" class="button button-primary button-compact">+ Tambah soal</a>
</div>
<div class="admin-v1-metrics" aria-label="Ringkasan bank soal">
  <div><strong>{{ $total }}</strong><span>Total soal</span></div><div><strong>{{ $drafts }}</strong><span>Draft</span></div><div><strong>{{ $published }}</strong><span>Terbit</span></div>
</div>
<section class="admin-v1-panel" aria-labelledby="questions-list-title">
  <div class="admin-v1-panel-top"><div><h2 id="questions-list-title">Daftar soal</h2><p>Soal contoh di website publik tidak otomatis menjadi soal produksi.</p></div></div>
  <div class="admin-v1-filters" data-questions-table data-url="{{ route('questions.admin.index') }}">
    <label class="admin-v1-field">Cari soal<input id="questions-search" type="search" placeholder="Kode atau isi pertanyaan" autocomplete="off"></label>
    <label class="admin-v1-field">Materi<select id="questions-subject"><option value="">Semua materi</option>@foreach($subjects as $subject)<option value="{{ $subject->code }}">{{ $subject->code }}</option>@endforeach</select></label>
    <label class="admin-v1-field">Status<select id="questions-status"><option value="">Semua status</option><option value="draft">Draft</option><option value="published">Terbit</option><option value="archived">Arsip</option></select></label>
    <button type="button" class="button button-outline button-compact" id="questions-refresh">Muat ulang</button>
  </div>
  <div class="table-scroll"><table class="data-table admin-v1-table"><thead><tr><th scope="col">Kode</th><th scope="col">Materi</th><th scope="col">Pertanyaan</th><th scope="col">Status</th><th scope="col">Dibuat</th><th scope="col">Aksi</th></tr></thead><tbody id="questions-rows"><tr><td colspan="6">Memuat soal…</td></tr></tbody></table></div>
  <div class="admin-v1-table-foot"><span id="questions-info" aria-live="polite"></span><div><button type="button" id="questions-prev" class="button button-outline button-compact">Sebelumnya</button><button type="button" id="questions-next" class="button button-outline button-compact">Berikutnya</button></div></div>
</section>
@endsection
