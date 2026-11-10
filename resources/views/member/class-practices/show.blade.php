@extends('layouts.workspace')
@section('title',$practice->title)
@section('content')
<div class="workspace-heading"><div><span class="workspace-label">LATIHAN KELAS</span><h1>{{ $practice->title }}</h1><p>{{ $practice->description }}</p></div><a class="text-link" href="{{ route('member.class-practices.index',$class) }}">← Daftar latihan</a></div>
<section class="workspace-note"><h2>Petunjuk</h2><p>{{ $practice->items_count }} soal · Durasi {{ $practice->duration_minutes }} menit untuk seluruh sesi · Maksimal {{ $practice->max_attempts }} percobaan.</p><p>Jawaban disimpan otomatis. Kunci dan pembahasan hanya tersedia setelah sesi selesai. Timer berlanjut meskipun browser ditutup.</p></section>
@if($attempts->count() >= $practice->max_attempts && !$attempts->contains('status','in_progress'))
<p>Batas percobaan tercapai. Lihat hasil pada riwayat latihan.</p>
@else
<form method="POST" action="{{ route('member.class-practices.start',[$class,$practice]) }}">@csrf <button class="button button-primary" type="submit">{{ $attempts->contains('status','in_progress') ? 'Lanjutkan sesi' : 'Mulai latihan' }}</button></form>
@endif
@if($attempts->isNotEmpty())
<h2>Riwayat percobaan</h2><div class="workspace-lines">@foreach($attempts as $attempt)
<article class="workspace-line"><div>Percobaan {{ $attempt->attempt_number }} · {{ $attempt->status }} @if($attempt->status!=='in_progress') · {{ $attempt->score_percent }}% @endif</div><a href="{{ $attempt->status==='in_progress' ? route('member.class-practices.take',[$class,$attempt]) : route('member.class-practices.result',[$class,$attempt]) }}">{{ $attempt->status==='in_progress'?'Lanjutkan':'Hasil' }}</a></article>
@endforeach</div>
@endif
@endsection
