@extends('layouts.workspace')
@section('title','Mengerjakan '.$attempt->version->title)
@section('content')
@include('components.form-errors')
<div class="member-exam-heading qq-exam-head">
    <div><span class="workspace-label">QUIZ KILAT · V{{ $attempt->version->version_number }}</span>
        <h1>{{ $attempt->version->title }}</h1>
        <p id="qq-heading-progress">Soal {{ $attempt->current_index + 1 }} dari {{ $attempt->total_questions }} · {{ $attempt->version->seconds_per_question }} detik/soal</p>
    </div>
    <div class="timer"><strong id="qq-countdown" aria-live="off">{{ $attempt->phase === 'feedback' ? '5 detik' : '--' }}</strong></div>
</div>
@include('member.quick-quizzes.partials.exam')
<p class="member-disclaimer">Jangan membuka dua tab dengan sesi berbeda. Sesi yang terputus dapat dipulihkan selama sekitar 30 menit sejak aktivitas terakhir.</p>
@vite('resources/js/member-quick-quizzes.js')
@endsection
