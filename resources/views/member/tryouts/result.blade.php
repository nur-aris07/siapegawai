@extends('layouts.workspace')
@section('title','Hasil '.$attempt->package->name)
@section('content')
<div class="workspace-heading"><div><span class="workspace-label">HASIL TRYOUT</span><h1>{{ $attempt->package->name }}</h1><p>Percobaan {{ $attempt->attempt_number }} · {{ $attempt->status==='timed_out'?'Waktu habis':'Dikumpulkan' }} · {{ $attempt->finished_at?->format('d M Y H:i') }}</p></div><a class="button button-outline" href="{{ route('member.tryouts.index') }}">Kembali ke daftar</a></div>
<div class="member-result-summary"><div><strong>{{ $attempt->correct_count }} / {{ $attempt->total_questions }}</strong><span>Jawaban benar</span></div><div><strong>{{ $attempt->answered_count }} / {{ $attempt->total_questions }}</strong><span>Soal dijawab</span></div><div><strong>{{ $attempt->score_percent }}%</strong><span>Skor latihan</span></div></div>
@include('member.tryouts.partials.subject-analysis', ['subjectAnalysis' => $subjectAnalysis, 'headingId' => 'member-result-subject-title', 'title' => 'Hasil per materi pada percobaan ini'])
<h2 class="member-review-title">Pembahasan tiap soal</h2>
<div class="member-review-list">@foreach($items as $n=>$item)
@php($saved=$answers->get($item->id))
@php($correct=$item->version?->options?->firstWhere('is_correct',true))
<article class="member-review-item"><div class="member-question-meta">Soal {{ $n+1 }} · {{ $item->version?->question?->code }} · v{{ $item->version?->version_number }}</div><h3>{{ $item->version?->body }}</h3><p>Jawabanmu: <strong>{{ $saved?->answer_label ?: 'Tidak dijawab' }}</strong> · Kunci: <strong>{{ $correct?->label ?: '—' }}</strong> @if($saved?->answer_label && $correct?->label===$saved->answer_label)<span class="member-right">Benar</span>@else<span class="member-wrong">Belum tepat</span>@endif</p><p class="member-explanation">{{ $item->version?->explanation }}</p></article>
@endforeach</div>
<p class="member-disclaimer">Hasil latihan berdasarkan versi soal yang dibekukan saat template diterbitkan. Penilaian ini bukan simulasi resmi BKN.</p>
@endsection
