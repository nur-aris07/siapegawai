@extends('layouts.workspace')
@section('title','Mengerjakan '.$attempt->version->title)
@section('content')
@include('components.form-errors')
<div class="member-exam-heading qq-exam-head"><div><span class="workspace-label">QUIZ KILAT · V{{ $attempt->version->version_number }}</span><h1>{{ $attempt->version->title }}</h1><p>Soal {{ $attempt->current_index+1 }} dari {{ $attempt->total_questions }} · {{ $attempt->version->seconds_per_question }} detik/soal</p></div><div class="timer"><strong id="qq-countdown" aria-live="off">{{ $attempt->phase==='feedback' ? 'Feedback' : '--' }}</strong></div></div>
<section class="qq-exam" id="qq-exam" data-phase="{{ $attempt->phase }}" data-expires-ms="{{ $attempt->question_expires_at->getTimestampMs() }}" data-server-now-ms="{{ now()->getTimestampMs() }}" data-next-url="{{ route('member.quick-quizzes.next',$attempt) }}" data-answer-url="{{ route('member.quick-quizzes.answer',$attempt) }}">
<div class="qq-exam-progress" aria-label="Progres Quiz Kilat"><span>Soal {{ $attempt->current_index+1 }}/{{ $attempt->total_questions }}</span><span>{{ $attempt->phase==='feedback'?'Jawaban terkunci':'Pilih satu jawaban' }}</span></div>
<div class="qq-question-label">{{ $item->subject_code_snapshot ?? 'SOAL' }} · {{ $item->topic_name_snapshot ?? 'Quiz Kilat' }}</div>
<h2>{{ $item->version->body }}</h2>
@if($attempt->phase==='question')
<form id="qq-answer-form" action="{{ route('member.quick-quizzes.answer',$attempt) }}" method="POST">@csrf<input type="hidden" name="item_id" value="{{ $item->id }}">
<fieldset class="qq-answers"><legend>Pilih jawaban untuk mengunci soal</legend>
@foreach($item->version->options as $opt)
<label class="qq-answer-choice"><input type="radio" name="answer" value="{{ $opt->label }}" required><span class="qq-letter">{{ $opt->label }}</span><span>{{ $opt->body }}</span></label>
@endforeach
</fieldset><button class="button button-primary" id="qq-answer-submit" type="submit">Kunci jawaban</button><p class="qq-help" id="qq-save-status" role="status" aria-live="polite">Sisa waktu dihitung oleh server. Jawaban tidak dapat diubah setelah terkunci.</p></form>
@else
<div class="qq-feedback" role="status"><strong>{{ $answer?->status==='timeout'?'Waktu habis':($answer?->is_correct?'Jawaban benar':'Jawaban belum tepat') }}</strong><p>Jawaban Anda: {{ $answer?->answer_label ?? 'Tidak dijawab' }} · Kunci benar: <strong>{{ $correct?->label }}</strong></p><div class="qq-explanation"><h3>Pembahasan</h3><p>{{ $item->version->explanation }}</p></div></div>
<form method="POST" action="{{ route('member.quick-quizzes.next',$attempt) }}" id="qq-next-form">@csrf<button type="submit" class="button button-primary" id="qq-next-button">{{ $attempt->current_index+1===$attempt->total_questions?'Lihat hasil':'Soal berikutnya →' }}</button><p class="qq-help" id="qq-feedback-status" role="status">Feedback ditampilkan 3 detik. Jika koneksi terputus, buka kembali sesi untuk melihatnya lagi.</p></form>
@endif
</section>
<p class="member-disclaimer">Jangan menutup dua tab dengan sesi berbeda. Sesi yang terputus dapat dipulihkan selama sekitar 30 menit sejak aktivitas terakhir.</p>
@vite('resources/js/member-quick-quizzes.js')
@endsection
