@extends('layouts.workspace')
@section('title','Riwayat Quiz Kilat')
@section('content')
<div class="workspace-heading"><div><a class="task-back" href="{{ route('member.quick-quizzes.index') }}">← Quiz Kilat</a><h1>Riwayat Quiz Kilat</h1><p>Hasil tersimpan berdasarkan versi soal dan versi quiz saat pengerjaan.</p></div></div>
@if($attempts->isEmpty())<section class="workspace-note"><strong>Belum ada hasil Quiz Kilat</strong><p>Mulai quiz yang sudah terbit ketika kuota harian Anda tersedia.</p></section>
@else
<div class="workspace-lines qq-list">@foreach($attempts as $attempt)
<article class="workspace-line"><div><span class="workspace-label">V{{ $attempt->version?->version_number }} · {{ $attempt->status==='completed'?'SELESAI':'TERHENTI' }}</span><h2>{{ $attempt->version?->title }}</h2><p>{{ $attempt->correct_count }}/{{ $attempt->total_questions }} benar · {{ number_format($attempt->score_percent,2,',','.') }}% · {{ $attempt->finished_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</p></div><a class="text-link" href="{{ route('member.quick-quizzes.result',$attempt) }}">Lihat review →</a></article>
@endforeach</div>{{ $attempts->links() }}
@endif
@endsection
