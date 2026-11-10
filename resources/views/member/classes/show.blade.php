@extends('layouts.workspace')
@section('title', $learningClass->title)
@section('content')
<div class="workspace-heading">
  <div>
    <span class="workspace-label">KELAS SAYA</span>
    <h1>{{ $learningClass->title }}</h1>
    <p>{{ $learningClass->description }}</p>
  </div>
  <a class="text-link" href="{{ route('member.classes.index') }}">← Kembali ke kelas saya</a>
</div>
@if($learningClass->syllabus)
<section class="workspace-note">
  <strong>Silabus</strong>
  <p class="s7-content">{{ $learningClass->syllabus }}</p>
</section>
@endif
<section class="workspace-note">
  <strong>Progres belajar: {{ $progress['completed'] }} dari {{ $progress['total'] }} materi selesai</strong>
  <p>{{ $progress['total'] > 0 ? $progress['percent'].'% selesai' : 'Materi belum tersedia' }}. Progres tercatat pada akun ini dan tidak hilang jika akses kelas dicabut sementara.</p>
</section>
<section class="workspace-note"><h2>Latihan Kelas</h2><p>Uji pemahaman setelah belajar. Hasil Latihan Kelas disimpan terpisah dari tryout CAT dan progres materi.</p><a class="button button-outline" href="{{ route('member.class-practices.index',$learningClass) }}">Buka Latihan Kelas</a></section>
<h2>Materi belajar</h2>
@if($materials->isEmpty())
<section class="workspace-note"><p>Belum ada materi yang diterbitkan untuk kelas ini.</p></section>
@else
<div class="workspace-lines">
@foreach($materials as $material)
<article class="workspace-line">
  <div>
    <span class="workspace-label">MATERI {{ $loop->iteration }} · {{ $material->completed_by_me_count ? 'SELESAI' : 'BELUM SELESAI' }}</span>
    <h2>{{ $material->title }}</h2>
    <p class="s7-content">{{ $material->content }}</p>
    @if($material->completed_by_me_count)
      <p><strong>✓ Sudah ditandai selesai</strong></p>
    @else
      <form method="POST" action="{{ route('member.classes.material.complete', [$learningClass, $material]) }}">
        @csrf
        <button type="submit" class="button button-outline">Tandai materi selesai</button>
      </form>
    @endif
  </div>
</article>
@endforeach
</div>
@endif
<p class="member-disclaimer">Konten kelas hanya untuk peserta dengan enrollment aktif. Jangan membagikan materi atau informasi akun kepada pihak lain.</p>
@endsection
