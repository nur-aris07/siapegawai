@extends('layouts.workspace')
@section('title','Latihan Kelas')
@section('content')
<div class="workspace-heading"><div><span class="workspace-label">LATIHAN KELAS</span><h1>{{ $class->title }}</h1><p>Kerjakan evaluasi kelas. Hasil tersimpan terpisah dari tryout CAT.</p></div><a class="text-link" href="{{ route('member.classes.show',$class) }}">← Detail kelas</a></div>
<h2>Latihan tersedia</h2><div class="workspace-lines">
@forelse($practices as $practice)
<article class="workspace-line"><div><h2>{{ $practice->title }}</h2><p>{{ $practice->items_count }} soal · {{ $practice->duration_minutes }} menit · Maks. {{ $practice->max_attempts }} percobaan</p><p>{{ $practice->description }}</p></div><a class="button button-primary" href="{{ route('member.class-practices.show',[$class,$practice]) }}">Lihat latihan</a></article>
@empty
<section class="workspace-note"><p>Belum ada latihan terbit di kelas ini.</p></section>
@endforelse
</div>
<h2>Riwayat Latihan Kelas</h2><div class="workspace-lines">
@forelse($attempts as $attempt)
<article class="workspace-line"><div><strong>{{ $attempt->practice?->title ?? 'Latihan Kelas' }}</strong><p>Percobaan {{ $attempt->attempt_number }} · {{ $attempt->status==='in_progress'?'Sedang dikerjakan':($attempt->status==='timed_out'?'Waktu habis':'Selesai') }} @if($attempt->status!=='in_progress') · Skor {{ $attempt->score_percent }}% @endif</p></div><a class="button button-outline button-compact" href="{{ $attempt->status==='in_progress' ? route('member.class-practices.take',[$class,$attempt]) : route('member.class-practices.result',[$class,$attempt]) }}">{{ $attempt->status==='in_progress'?'Lanjutkan':'Lihat hasil' }}</a></article>
@empty
<p>Belum ada riwayat latihan pada kelas ini.</p>
@endforelse
</div><p class="member-disclaimer">Riwayat ini bukan riwayat CAT/Tryout dan tidak mengubah progres materi selesai.</p>
@endsection
