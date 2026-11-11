@extends('layouts.workspace')
@section('title','Hasil Quiz Kilat')
@section('content')
<div class="workspace-heading"><div><a href="{{ route('member.quick-quizzes.history') }}" class="task-back">← Riwayat Quiz Kilat</a><h1>Hasil: {{ $attempt->version->title }}</h1><p>Versi {{ $attempt->version->version_number }} · {{ $attempt->status==='completed'?'Selesai':'Sesi terhenti karena batas pemulihan terlampaui' }}</p></div></div>
<section class="qq-result-summary"><div><span class="workspace-label">NILAI</span><strong>{{ number_format($attempt->score_percent,2,',','.') }}%</strong></div><div><span class="workspace-label">JAWABAN BENAR</span><strong>{{ $attempt->correct_count }} / {{ $attempt->total_questions }}</strong></div><div><span class="workspace-label">DIBAHAS</span><strong>{{ $answers->count() }} soal</strong></div></section>
<h2 class="qq-section-heading">Review jawaban</h2>
@if($attempt->status==='abandoned')<p class="qq-help">Hanya soal yang sudah disajikan dan dikunci yang dibuka pada review ini. Soal yang belum tampil tidak dihitung sebagai benar.</p>@endif
<div class="qq-review-list">
@foreach($answers as $answer)
@php($item=$answer->item)
@php($key=$item?->version?->options?->firstWhere('is_correct',true))
<article class="qq-review-item"><span class="workspace-label">SOAL {{ $item?->sort_order }} · {{ $item?->subject_code_snapshot }}</span><h3>{{ $item?->version?->body }}</h3><p><strong>{{ $answer->is_correct?'Benar':($answer->status==='timeout'?'Waktu habis':'Salah') }}</strong> · Jawaban: {{ $answer->answer_label ?? '—' }} · Kunci: {{ $key?->label }}</p><ul>@foreach($item?->version?->options ?? [] as $option)<li><strong>{{ $option->label }}.</strong> {{ $option->body }}</li>@endforeach</ul><div class="qq-explanation"><strong>Pembahasan</strong><p>{{ $item?->version?->explanation }}</p></div></article>
@endforeach
</div>
<p class="member-disclaimer">Penilaian Quiz Kilat bersifat internal, bukan nilai resmi CPNS dan tidak digabung dengan CAT.</p>
@endsection
