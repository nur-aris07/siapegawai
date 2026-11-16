@extends('layouts.admin')
@section('title', $quiz ? 'Edit Quiz Kilat' : 'Buat Quiz Kilat')
@section('content')
<div class="admin-quick-quizzes">
  <div class="admin-v1-heading"><div><p class="admin-v1-crumb">Konten latihan / <a href="{{ route('admin.quick-quizzes.index') }}">Quiz Kilat</a> / {{ $quiz ? 'Pengelolaan' : 'Buat draft' }}</p>
    <h1>{{ $quiz ? 'Quiz Kilat: '.$quiz->code : 'Buat Quiz Kilat' }}</h1>
    <p>Jumlah soal saat publikasi: 30–40. Waktu berlaku untuk <strong>setiap soal</strong>; bukan waktu keseluruhan quiz.</p></div>
    <a class="button button-outline button-compact" href="{{ route('admin.quick-quizzes.index') }}">Kembali</a>
  </div>
  @if($quiz && $quiz->currentVersion)
  <section class="admin-v1-panel"><h2>Versi terbit tetap terkunci</h2>
    <p class="admin-v1-muted">Versi v{{ $quiz->currentVersion->version_number }}: {{ $quiz->currentVersion->title }} · {{ $quiz->currentVersion->seconds_per_question }} detik/soal.</p>
    <p>Revisi disiapkan dalam draft baru, tanpa menimpa versi yang sudah terbit.</p>
    @if(!$draft && $quiz->status==='published')
      <form class="admin-qq-action" method="POST" action="{{ route('admin.quick-quizzes.revision.start',$quiz) }}">@csrf<button class="button button-outline button-compact" type="submit">Buat revisi v{{ $quiz->currentVersion->version_number+1 }}</button></form>
    @endif
  </section>
  @endif
  @if($quiz && $quiz->status==='archived')
    <section class="admin-v1-panel"><h2>Quiz diarsipkan</h2><p>Riwayat versi disimpan. Pengeditan dan penerbitan baru tidak tersedia.</p></section>
  @elseif($quiz && !$draft)
    <section class="admin-v1-panel"><h2>Tidak ada draft aktif</h2><p>Buat revisi untuk menyusun versi berikutnya.</p></section>
  @else
  <form method="POST" class="admin-qq-editor sg-editor-layout" action="{{ $quiz ? route('admin.quick-quizzes.update',$quiz) : route('admin.quick-quizzes.store') }}" data-qq-editor data-search-url="{{ route('admin.quick-quizzes.search') }}">
    @csrf @if($quiz) @method('PUT') @endif
    <section class="admin-v1-panel admin-v1-form-panel">
      <h2>Identitas dan aturan pengerjaan</h2>
      @if(!$quiz)<label class="admin-v1-field">Kode quiz<input name="code" value="{{ old('code') }}" maxlength="48" pattern="[A-Za-z][A-Za-z0-9-]*" required></label>@endif
      <label class="admin-v1-field">Judul quiz<input name="title" maxlength="180" required value="{{ old('title',$draft?->title ?? '') }}"></label>
      <label class="admin-v1-field">Deskripsi<textarea name="description" rows="3" maxlength="2500">{{ old('description',$draft?->description??'') }}</textarea></label>
      <label class="admin-v1-field">Waktu tiap soal (5–180 detik)<input type="number" min="5" max="180" name="seconds_per_question" required value="{{ old('seconds_per_question',$draft?->seconds_per_question??'') }}"></label>
      <p class="admin-v1-muted">Jawaban akan dikunci saat dipilih/timeout. Feedback tetap 3 detik menggunakan pembahasan dari versi soal yang sama. Pengaturan timer harus tersimpan oleh server.</p>
    <div class="sg-editor-desktop-actions"><div class="admin-qq-actions"><button type="submit" class="button button-primary">Simpan draft</button></div></div>
</section>
    @include('admin.bulk-assignment.composer',[
      'composerCreate'=>!$quiz, 'composerMax'=>40, 'composerDomain'=>'qq',
      'composerSearchUrl'=>route('admin.quick-quizzes.search')
    ])
    <div class="admin-qq-actions"><button type="submit" class="button button-primary">Simpan draft</button></div>
  </form>
  @if($quiz && $draft)
    @include('admin.bulk-assignment.picker',['bulkDomain'=>'qq','bulkTarget'=>$quiz->id])
    <section class="admin-v1-panel"><h2>Terbitkan versi v{{ $draft->version_number }}</h2>
      <p>Pastikan susunan sudah disimpan dan ditinjau. Publikasi mengunci versi ini; pengerjaan peserta tetap ditutup sampai kuota diimplementasikan.</p>
      <form method="POST" action="{{ route('admin.quick-quizzes.publish',$quiz) }}">@csrf
        <label class="admin-qq-checkbox"><input type="checkbox" name="confirmed_review" value="1" required> Saya telah meninjau jumlah, urutan, timer, dan kelayakan soal.</label>
        <button type="submit" class="button button-primary button-compact">Terbitkan versi v{{ $draft->version_number }}</button>
      </form>
    </section>
  @endif
  @endif
  @if($quiz && $quiz->status==='published' && !$draft)
    <section class="admin-v1-panel"><h2>Arsipkan quiz</h2>
      <p>Quiz terbit tidak dihapus. Tindakan ini menghapus akses publik nanti, bukan riwayat versi.</p>
      <form method="POST" action="{{ route('admin.quick-quizzes.archive',$quiz) }}">@csrf
        <label class="admin-v1-field">Alasan pengarsipan<textarea name="reason" required minlength="10" maxlength="500" rows="2"></textarea></label>
        <button type="submit" class="button button-outline button-compact">Arsipkan quiz</button>
      </form>
    </section>
  @endif
  @if($quiz)
    <section class="admin-v1-panel"><h2>Riwayat versi</h2>
      <div class="admin-qq-version-history">
      @foreach($quiz->versions()->withCount('items')->orderByDesc('version_number')->get() as $version)
        <p>v{{ $version->version_number }} · {{ $version->status === 'published' ? 'Terbit' : 'Draft' }} · {{ $version->items_count }} soal · {{ $version->seconds_per_question }} detik/soal · {{ $version->title }}</p>
      @endforeach
      </div>
    </section>
  @endif
</div>
@endsection
