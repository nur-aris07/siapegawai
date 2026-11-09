@extends('layouts.workspace')
@section('title','Kelas saya')
@section('content')
<div class="workspace-heading"><div><span class="workspace-label">BELAJAR ANGGOTA</span><h1>Kelas saya</h1><p>Hanya kelas yang aksesnya sudah diberikan oleh pengelola yang muncul di sini.</p></div></div>
@include('components.form-errors')
@if($enrollments->isEmpty())
<section class="workspace-note"><strong>Belum mengikuti kelas</strong><p>Akun aktif tidak otomatis mendapatkan akses kelas. Pengelola perlu memberikan enrollment terlebih dahulu.</p><a class="text-link" href="{{ route('page.kelas') }}">Lihat informasi kelas publik →</a></section>
@else
<div class="workspace-lines">
@foreach($enrollments as $enrollment)
@php
$item = $enrollment->learningClass;
$summary = $item ? ($progress[$item->id] ?? ['total'=>0, 'completed'=>0, 'percent'=>0]) : null;
@endphp
@if($item)
<article class="workspace-line">
  <div>
    <span class="workspace-label">{{ $item->status==='open' ? 'KELAS DIBUKA' : 'KELAS TERDAFTAR' }}</span>
    <h2>{{ $item->title }}</h2>
    <p>{{ $item->description }}</p>
    <p><strong>Progres: {{ $summary['completed'] }} dari {{ $summary['total'] }} materi</strong> · {{ $summary['total'] > 0 ? $summary['percent'].'% selesai' : 'Belum ada materi terbit' }}</p>
  </div>
  <a class="text-link" href="{{ route('member.classes.show', $item) }}">Buka materi →</a>
</article>
@endif
@endforeach
</div>
{{ $enrollments->links() }}
@endif
@endsection
