@extends('layouts.admin')
@section('title','Kelola Soal '.$question->code)
@section('content')
@php
    $activeVersion = $question->currentVersion;
    $pendingDraft = $version?->review_status === 'draft' ? $version : null;
@endphp
<div class="admin-v1-heading">
  <div>
    <p class="admin-v1-crumb"><a href="{{ route('questions.admin.index') }}">Bank soal</a> / {{ $question->code }}</p>
    <h1>Kelola soal {{ $question->code }}</h1>
    @if($question->status === 'archived')
      <p>Soal diarsipkan · Riwayat versi tetap tersimpan.</p>
    @elseif($activeVersion && $pendingDraft)
      <p><strong>Terbit v{{ $activeVersion->version_number }}</strong> · <strong>Draft revisi v{{ $pendingDraft->version_number }}</strong> belum diterbitkan.</p>
    @elseif($activeVersion)
      <p><strong>Terbit v{{ $activeVersion->version_number }}</strong> · Tidak ada draft revisi yang menunggu publikasi.</p>
    @else
      <p>Belum diterbitkan · Draft v{{ $pendingDraft?->version_number ?? '—' }}.</p>
    @endif
  </div>
</div>
@if($question->status === 'archived')
  <section class="admin-v1-panel"><h2>Soal diarsipkan</h2><p>Konten tersimpan untuk audit. Soal arsip tidak dapat diedit atau diterbitkan.</p><div class="admin-v1-text-block">{{ $version?->body }}</div></section>
@else
  @include('questions.admin.form', ['action'=>route('questions.admin.update',$question), 'method'=>'PUT'])
  <div class="admin-v1-decision-grid">
    <section class="admin-v1-panel">
      <h2>Review dan terbitkan</h2>
      <p>Publikasi dilakukan terpisah dari penyimpanan draft. Periksa pertanyaan, pilihan, kunci, serta pembahasan sebelum menerbitkan.</p>
      @if($pendingDraft)
        @if($activeVersion)
          <p class="admin-v1-muted">Versi {{ $activeVersion->version_number }} masih aktif. Menerbitkan revisi akan menggantikan versi aktif dengan versi {{ $pendingDraft->version_number }}. Jika isi draft sama persis dengan versi aktif, sistem akan menolak publikasi duplikat. Gunakan Batalkan draft revisi bila perubahan tidak diperlukan.</p>
        @endif
        <form method="POST" action="{{ route('questions.admin.publish',$question) }}" class="admin-v1-action-form">@csrf
          <label class="check-line"><input type="checkbox" name="confirmed_review" value="1" required><span>Saya sudah memeriksa kunci dan pembahasan soal ini.</span></label>
          <button type="submit" class="button button-primary button-compact">{{ $activeVersion ? 'Terbitkan revisi v'.$pendingDraft->version_number : 'Terbitkan versi '.$pendingDraft->version_number }}</button>
        </form>
        @if($activeVersion)
          <details class="admin-v1-note">
            <summary>Batalkan draft revisi v{{ $pendingDraft->version_number }}</summary>
            <p>Buang draft revisi ini dan kembali menggunakan versi terbit v{{ $activeVersion->version_number }}. Tindakan ini tidak dapat diurungkan; keputusan tetap dicatat pada audit.</p>
            <form method="POST" action="{{ route('questions.admin.discard-draft', $question) }}" class="admin-v1-action-form">
              @csrf
              <label class="admin-v1-field">Alasan pembatalan draft (wajib)
                <textarea name="discard_reason" minlength="10" maxlength="500" rows="2" required placeholder="Contoh: Revisi tidak diperlukan karena kunci versi aktif sudah benar">{{ old('discard_reason') }}</textarea>
              </label>
              <label class="check-line"><input type="checkbox" name="confirmed_discard" value="1" required><span>Saya memahami draft v{{ $pendingDraft->version_number }} akan dibuang tanpa mengubah versi terbit.</span></label>
              <button type="submit" class="button button-outline button-compact">Batalkan draft revisi v{{ $pendingDraft->version_number }}</button>
            </form>
          </details>
        @endif
      @else
        <p class="admin-v1-muted">Tidak ada perubahan draft yang belum diterbitkan.</p>
      @endif
    </section>
    <section class="admin-v1-panel"><h2>Arsipkan soal</h2><p>Arsip menghapus soal dari katalog produksi aktif, tetapi tidak menghapus versi atau audit.</p>
      <form method="POST" action="{{ route('questions.admin.archive',$question) }}" class="admin-v1-action-form">@csrf
        <label class="admin-v1-field">Alasan arsip (wajib)<textarea name="archive_reason" required minlength="10" maxlength="500" rows="2" placeholder="Jelaskan alasan pengarsipan"></textarea></label>
        <button type="submit" class="button button-outline button-compact">Arsipkan soal</button>
      </form>
    </section>
  </div>
@endif
@endsection
