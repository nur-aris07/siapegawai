@extends('layouts.admin')
@section('title','Kelola Materi '.$subject->code)
@section('content')
<div class="admin-v1-heading"><div><p class="admin-v1-crumb"><a href="{{ route('subjects.admin.index') }}">Kategori materi</a> / {{ $subject->code }}</p><h1>{{ $subject->code }} · {{ $subject->name }}</h1><p>Topik yang sudah digunakan soal harus dipertahankan untuk menjaga riwayat materi.</p></div></div>
<div class="admin-v1-catalog-grid">
  <section class="admin-v1-panel">
    <h2>Pengaturan kategori</h2>
    <form method="POST" action="{{ route('subjects.admin.update',$subject) }}" class="admin-v1-action-form">@csrf @method('PUT')
      <label class="admin-v1-field">Kode permanen<input value="{{ $subject->code }}" disabled></label>
      <label class="admin-v1-field">Nama kategori<input name="name" value="{{ old('name',$subject->name) }}" maxlength="120" required></label>
      <div class="admin-v1-editor-row">
        <label class="admin-v1-field">Status<select name="status"><option value="active" @selected($subject->status === 'active')>Aktif</option><option value="inactive" @selected($subject->status === 'inactive')>Nonaktif</option></select></label>
        <label class="admin-v1-field">Urutan<input name="sort_order" type="number" min="0" max="9999" value="{{ $subject->sort_order }}" required></label>
      </div>
      <button class="button button-primary button-compact" type="submit">Simpan kategori</button>
    </form>
    @if($subject->topics()->count()===0)
      <details class="admin-v1-note"><summary>Hapus kategori kosong</summary><form method="POST" action="{{ route('subjects.admin.destroy',$subject) }}" class="admin-v1-action-form">@csrf @method('DELETE')<label class="check-line"><input name="confirmed_delete" type="checkbox" value="1" required> Saya memahami kategori ini akan dihapus.</label><button class="button button-outline button-compact" type="submit">Hapus kategori</button></form></details>
    @endif
  </section>
  <section class="admin-v1-panel">
    <h2>Tambah topik</h2><p>Topik baru akan tersedia di formulir soal jika kategori aktif.</p>
    <form method="POST" action="{{ route('subjects.admin.topics.store',$subject) }}" class="admin-v1-action-form">@csrf
      <label class="admin-v1-field">Nama topik<input name="name" value="{{ old('name') }}" maxlength="150" required></label>
      <label class="admin-v1-field">Urutan<input type="number" name="sort_order" min="0" max="9999" value="0"></label>
      <button class="button button-primary button-compact" type="submit">Tambah topik</button>
    </form>
  </section>
</div>
<section class="admin-v1-panel admin-v1-catalog-topics"><h2>Topik dalam {{ $subject->code }}</h2>
  @forelse($topics as $topic)
    <div class="admin-v1-topic-row">
      <form method="POST" action="{{ route('subjects.admin.topics.update',$topic) }}" class="admin-v1-topic-form">@csrf @method('PUT')
        <div class="admin-v1-topic-heading"><strong>{{ $topic->slug }}</strong><span class="admin-v1-muted">{{ $topic->questions_count }} soal</span></div>
        <label class="admin-v1-field">Nama topik<input name="name" value="{{ $topic->name }}" maxlength="150" required></label>
        <label class="admin-v1-field">Status<select name="status"><option value="active" @selected($topic->status==='active')>Aktif</option><option value="inactive" @selected($topic->status==='inactive')>Nonaktif</option></select></label>
        <label class="admin-v1-field">Urutan<input type="number" name="sort_order" value="{{ $topic->sort_order }}" min="0" max="9999" required></label>
        <button class="button button-outline button-compact" type="submit">Simpan</button>
      </form>
      @if($topic->questions_count===0)
        <form method="POST" action="{{ route('subjects.admin.topics.destroy',$topic) }}" class="admin-v1-topic-delete">@csrf @method('DELETE')<label class="check-line"><input type="checkbox" name="confirmed_delete" value="1" required> Hapus topik kosong</label><button type="submit" class="button button-outline button-compact">Hapus</button></form>
      @else <p class="admin-v1-muted">Topik memiliki soal, gunakan status Nonaktif alih-alih menghapus.</p> @endif
    </div>
  @empty<p>Belum ada topik.</p>@endforelse
</section>
@endsection
