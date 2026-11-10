@extends('layouts.admin')
@section('title','Kelola kelas')
@section('content')
<div class="admin-v1-class-editor">
<div class="admin-v1-heading"><div><p class="admin-v1-crumb"><a href="{{ route('admin.classes.index') }}">Kelas</a> / Detail</p><h1>{{ $learningClass->title }}</h1><p>Kelola status, materi privat, dan peserta yang diberi akses.</p></div></div>
<div class="admin-v1-catalog-grid">
<section class="admin-v1-panel"><h2>Pengaturan kelas</h2>
<form action="{{ route('admin.classes.update',$learningClass) }}" method="POST" class="admin-v1-action-form">@csrf @method('PUT')
<label class="admin-v1-field">Nama<input name="title" required minlength="5" maxlength="160" value="{{ old('title',$learningClass->title) }}"></label>
<label class="admin-v1-field">Deskripsi<textarea name="description" required minlength="15" maxlength="3000" rows="4">{{ old('description',$learningClass->description) }}</textarea></label>
<label class="admin-v1-field">Silabus<textarea name="syllabus" maxlength="6000" rows="4">{{ old('syllabus',$learningClass->syllabus) }}</textarea></label>
<label class="admin-v1-field">Kapasitas<input type="number" name="capacity" min="1" max="100000" value="{{ old('capacity',$learningClass->capacity) }}"></label>
<label class="admin-v1-field">Status<select name="status" required>
@foreach(['draft'=>'Draft','open'=>'Dibuka','closed'=>'Ditutup'] as $key=>$value)
<option value="{{ $key }}" @selected(old('status',$learningClass->status)===$key)>{{ $value }}</option>
@endforeach
</select></label>
<p class="admin-v1-muted">Untuk membuka kelas, terbitkan setidaknya satu materi. Kelas yang sudah memiliki peserta aktif tidak bisa dikembalikan ke Draft.</p>
<button class="button button-primary" type="submit">Simpan perubahan</button></form></section>
<section class="admin-v1-panel"><h2>Tambah materi teks</h2><p>Materi baru masuk sebagai draft. HTML yang diketik akan ditampilkan sebagai teks, bukan dieksekusi.</p>
<form method="POST" action="{{ route('admin.classes.material.store',$learningClass) }}" class="admin-v1-action-form">@csrf
<label class="admin-v1-field">Judul materi<input name="title" minlength="3" maxlength="160" required value="{{ old('title') }}"></label>
<label class="admin-v1-field">Isi materi<textarea name="content" rows="9" minlength="20" maxlength="20000" required>{{ old('content') }}</textarea></label>
<label class="admin-v1-field">Urutan<input type="number" name="sort_order" min="0" max="65535" value="{{ old('sort_order',0) }}"></label>
<label class="admin-v1-field">Bab (opsional)<select name="chapter_id"><option value="">Tanpa bab</option>@foreach($chapters as $chapter)<option value="{{ $chapter->id }}" @selected((string)old('chapter_id')===(string)$chapter->id)>{{ $chapter->title }}</option>@endforeach</select></label>
<button type="submit" class="button button-primary">Simpan materi draft</button></form></section>
</div>
<section class="admin-v1-panel admin-media-chapters">
  <h2>Bab pembelajaran (S7.5)</h2>
  <p class="admin-v1-muted">Kelompokkan materi ke bab. Materi terbit lama tetap utuh dan progres tidak dihitung ulang berdasarkan bab.</p>
  <form method="POST" action="{{ route('admin.classes.chapters.store', $learningClass) }}" class="admin-v1-action-form">@csrf
    <label class="admin-v1-field">Nama bab<input name="title" minlength="3" maxlength="160" required value="{{ old('title') }}"></label>
    <label class="admin-v1-field">Urutan bab<input type="number" name="sort_order" min="0" max="65535" value="0"></label>
    <button type="submit" class="button button-outline">Tambahkan bab</button>
  </form>
  @if($chapters->isNotEmpty())
  <ul class="admin-media-chapter-list">
    @foreach($chapters as $chapter)
    <li>{{ $chapter->title }} <span class="admin-v1-muted">· Urutan {{ $chapter->sort_order }}</span></li>
    @endforeach
  </ul>
  @endif
</section>
<section class="admin-v1-panel"><h2>Monitoring kelas (S7.4)</h2><p>Lihat progres materi, status akses peserta, dan hasil Latihan Kelas. Data hanya dibaca, tanpa mengubah nilai atau histori.</p><a class="button button-outline" href="{{ route('admin.classes.monitoring.index',$learningClass) }}">Buka monitoring kelas</a></section>
<section class="admin-v1-panel"><h2>Latihan Kelas (S7.3)</h2><p>Susun latihan dari bank soal terbit, atur timer dan batas percobaan, lalu publikasikan untuk peserta dengan enrollment aktif.</p><a class="button button-outline" href="{{ route('admin.class-practices.index',$learningClass) }}">Kelola Latihan Kelas</a></section>
<section class="admin-v1-panel admin-v1-catalog-topics"><h2>Materi kelas</h2>
@if($materials->isEmpty())
<p class="admin-v1-muted">Belum ada materi.</p>
@endif
@foreach($materials as $material)
<div class="admin-v1-topic-row">
  <div>
    <strong>{{ $material->title }}</strong>
    <p class="admin-v1-muted">{{ $material->status==='published' ? 'Terbit (tidak dapat diedit)' : 'Draft (dapat diperbaiki)' }} · Urutan {{ $material->sort_order }}</p>
    @if($material->chapter)<p class="admin-v1-muted">Bab: {{ $material->chapter->title }}</p>@endif
    @foreach($material->mediaAssets as $media)
      <p class="admin-v1-muted">{{ $media->kind === 'pdf' ? 'PDF privat' : 'Video eksternal' }}: {{ $media->title }}
      @if($media->kind === 'pdf')<a href="{{ route('admin.classes.media.download', [$learningClass, $material, $media]) }}">Unduh PDF</a>@endif
      </p>
    @endforeach
  </div>
  @if($material->status === 'draft')
  <div>
    <details>
      <summary>Perbaiki draft materi</summary>
      <form class="admin-v1-action-form" method="POST" action="{{ route('admin.classes.material.update',[$learningClass,$material]) }}">
        @csrf
        @method('PUT')
        <label class="admin-v1-field">Judul<input name="title" maxlength="160" minlength="3" required value="{{ $material->title }}"></label>
        <label class="admin-v1-field">Isi materi<textarea name="content" rows="8" maxlength="20000" minlength="20" required>{{ $material->content }}</textarea></label>
        <label class="admin-v1-field">Urutan<input name="sort_order" type="number" min="0" max="65535" required value="{{ $material->sort_order }}"></label>
        <label class="admin-v1-field">Bab (opsional)<select name="chapter_id"><option value="">Tanpa bab</option>@foreach($chapters as $chapter)<option value="{{ $chapter->id }}" @selected((string)$material->chapter_id===(string)$chapter->id)>{{ $chapter->title }}</option>@endforeach</select></label>
        <button class="button button-outline button-compact" type="submit">Simpan revisi draft</button>
      </form>
    </details>
    <details class="admin-media-details">
      <summary>Kelola lampiran draft</summary>
      <p class="admin-v1-muted">PDF maksimal 20 MB disimpan di disk privat. Tautan YouTube/Vimeo mengarah ke pihak ketiga dan tidak dapat dibatasi setelah dibuka.</p>
      <form method="POST" enctype="multipart/form-data" class="admin-v1-action-form" action="{{ route('admin.classes.media.pdf',[$learningClass,$material]) }}">@csrf
        <label class="admin-v1-field">Judul PDF<input name="title" minlength="3" maxlength="160" required></label>
        <label class="admin-v1-field">File PDF<input type="file" name="pdf" accept=".pdf,application/pdf" required></label>
        <button type="submit" class="button button-outline button-compact">Unggah PDF privat</button>
      </form>
      <form method="POST" class="admin-v1-action-form" action="{{ route('admin.classes.media.video',[$learningClass,$material]) }}">@csrf
        <label class="admin-v1-field">Judul video<input name="title" minlength="3" maxlength="160" required></label>
        <label class="admin-v1-field">Tautan HTTPS YouTube atau Vimeo<input type="url" name="external_url" maxlength="500" required></label>
        <button type="submit" class="button button-outline button-compact">Tambahkan tautan video</button>
      </form>
      @foreach($material->mediaAssets as $media)
      <form method="POST" class="admin-media-delete" action="{{ route('admin.classes.media.remove',[$learningClass,$material,$media]) }}">@csrf
        <label class="check-line"><input name="confirmed" type="checkbox" value="1" required> Hapus lampiran draft: {{ $media->title }}</label>
        <button type="submit" class="button button-outline button-compact">Hapus lampiran</button>
      </form>
      @endforeach
    </details>
    <form method="POST" action="{{ route('admin.classes.material.publish',[$learningClass,$material]) }}">
      @csrf
      <button class="button button-outline button-compact" type="submit">Terbitkan materi</button>
    </form>
  </div>
  @endif
</div>
@endforeach
</section>
<section class="admin-v1-panel admin-v1-catalog-topics"><h2>Akses peserta (manual)</h2>
@if(auth()->user()->role === 'superadmin')
<p>Hanya Superadmin dapat memberikan/mencabut akses. Gunakan ID peserta aktif dari Direktori peserta; tidak perlu membuat akun baru.</p>
<form method="POST" class="admin-v1-action-form" action="{{ route('admin.classes.enroll',$learningClass) }}">@csrf
<label class="admin-v1-field">ID peserta<input type="number" name="user_id" min="1" required value="{{ old('user_id') }}"></label>
<label class="admin-v1-field">Alasan pemberian akses<textarea name="reason" minlength="12" maxlength="500" required rows="2">{{ old('reason') }}</textarea></label>
<label class="check-line"><input type="checkbox" name="confirmed" value="1" required> Saya menyetujui pemberian akses kelas ini.</label>
<button type="submit" class="button button-primary">Berikan akses kelas</button>
</form>
@else
<p>Admin Operasional bisa mengelola materi, tetapi pemberian akses hanya tersedia untuk Superadmin.</p>
@endif
<div class="table-scroll"><table class="data-table admin-v1-table"><thead><tr><th>Peserta</th><th>Email</th><th>Aksi</th></tr></thead><tbody>
@forelse($enrollments as $enrollment)
<tr><td>{{ $enrollment->user?->name ?? 'Akun tidak ditemukan' }}</td><td>{{ $enrollment->user?->email ?? '—' }}</td><td>
@if(auth()->user()->role === 'superadmin' && $enrollment->user)
<form class="admin-v1-action-form" method="POST" action="{{ route('admin.classes.revoke',[$learningClass,$enrollment->user]) }}">@csrf
<label class="admin-v1-field">Alasan cabut akses<textarea name="reason" rows="2" required minlength="12" maxlength="500"></textarea></label>
<label class="check-line"><input type="checkbox" name="confirmed" value="1" required> Konfirmasi pencabutan</label>
<button type="submit" class="button button-outline button-compact">Cabut akses</button></form>
@else
—
@endif
</td></tr>
@empty
<tr><td colspan="3">Belum ada peserta dengan akses aktif.</td></tr>
@endforelse
</tbody></table></div>
</section>
</div>
@endsection
