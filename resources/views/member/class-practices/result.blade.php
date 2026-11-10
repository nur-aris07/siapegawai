@extends('layouts.workspace')
@section('title','Hasil Latihan Kelas')
@section('content')
<div class="workspace-heading"><div><span class="workspace-label">HASIL LATIHAN KELAS</span><h1>{{ $attempt->practice->title }}</h1><p>Percobaan {{ $attempt->attempt_number }} · {{ $attempt->status==='timed_out'?'Waktu habis':'Dikumpulkan' }} · {{ $attempt->finished_at?->format('d M Y H:i') }}</p></div><a class="button button-outline" href="{{ route('member.class-practices.index',$class) }}">Daftar latihan</a></div>
<div class="member-result-summary"><div><strong>{{ $attempt->correct_count }} / {{ $attempt->total_questions }}</strong><span>Jawaban benar</span></div><div><strong>{{ $attempt->answered_count }} / {{ $attempt->total_questions }}</strong><span>Soal dijawab</span></div><div><strong>{{ $attempt->score_percent }}%</strong><span>Skor latihan internal</span></div></div>
<h2 class="member-review-title">Pembahasan tiap soal</h2><div class="member-review-list">@foreach($items as $n=>$item)
@php($saved=$answers->get($item->id))
@php($correct=$item->version?->options?->firstWhere('is_correct',true))
<article class="member-review-item"><div class="member-question-meta">Soal {{ $n+1 }} · {{ $item->subject_code_snapshot ?? 'MATERI' }} · v{{ $item->version?->version_number }}</div><h3>{{ $item->version?->body }}</h3><p>Jawabanmu: <strong>{{ $saved?->answer_label ?: 'Tidak dijawab' }}</strong> · Kunci: <strong>{{ $correct?->label ?: '—' }}</strong> @if($saved?->answer_label && $correct?->label===$saved->answer_label)<span class="member-right">Benar</span>@else<span class="member-wrong">Belum tepat</span>@endif</p><p class="member-explanation">{{ $item->version?->explanation }}</p></article>
@endforeach</div><p class="member-disclaimer">Skor latihan kelas ini bukan skor resmi CPNS. Hasil latihan tidak masuk riwayat Tryout CAT atau progres materi selesai.</p>
@endsection
