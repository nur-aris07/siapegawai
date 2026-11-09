@extends('layouts.admin')
@section('title','Kategori Materi')
@section('content')
<div class="admin-v1-heading"><div><p class="admin-v1-crumb">Konten / Kategori dan topik</p><h1>Kategori materi</h1><p>Kelola kategori dan topik tanpa memutus hubungan dengan soal yang sudah tersimpan.</p></div></div>
<div class="admin-v1-catalog-grid">
  <section class="admin-v1-panel">
    <h2>Daftar kategori</h2><p>Pilih kategori untuk mengatur topik dan statusnya.</p>
    <div class="table-scroll"><table class="data-table admin-v1-table"><thead><tr><th>Kode</th><th>Nama</th><th>Topik</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
      @forelse($subjects as $subject)
        <tr><td><strong>{{ $subject->code }}</strong></td><td>{{ $subject->name }}</td><td>{{ $subject->topics_count }}</td><td><span class="admin-v1-status {{ $subject->status === 'active' ? 'published' : 'archived' }}">{{ $subject->status === 'active' ? 'Aktif' : 'Nonaktif' }}</span></td><td><a href="{{ route('subjects.admin.show',$subject) }}">Kelola</a></td></tr>
      @empty<tr><td colspan="5">Belum ada kategori.</td></tr>@endforelse
    </tbody></table></div>
  </section>
  <section class="admin-v1-panel">
    <h2>Tambah kategori</h2>
    <p>Kode kategori bersifat permanen. Contoh kode: TWK, TIU, TKP, SKB.</p>
    <form method="POST" action="{{ route('subjects.admin.store') }}" class="admin-v1-action-form">@csrf
      <label class="admin-v1-field">Kode kategori<input name="code" value="{{ old('code') }}" maxlength="12" placeholder="TWK" required></label>
      <label class="admin-v1-field">Nama kategori<input name="name" value="{{ old('name') }}" maxlength="120" required></label>
      <label class="admin-v1-field">Urutan<input type="number" name="sort_order" min="0" max="9999" value="{{ old('sort_order','0') }}"></label>
      <button type="submit" class="button button-primary button-compact">Tambah kategori</button>
    </form>
  </section>
</div>
@endsection
