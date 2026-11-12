@extends('layouts.workspace')
@section('title',$quiz->currentVersion->title)
@section('content')
<div class="workspace-heading"><div><a class="task-back" href="{{ route('member.quick-quizzes.index') }}">← Semua Quiz Kilat</a><h1>{{ $quiz->currentVersion->title }}</h1><p>{{ $quiz->currentVersion->description }}</p></div></div>
@include('components.form-errors')
@include('member.quick-quizzes.partials.quota')
<section class="workspace-note qq-notice"><strong>{{ $count }} soal · {{ $quiz->currentVersion->seconds_per_question }} detik per soal</strong><p>Jawaban dikunci saat dipilih. Setelah itu, kunci dan pembahasan dari soal terbit ditampilkan selama 5 detik pada kartu soal yang sama. Seluruh pembahasan dapat dibaca lagi setelah quiz selesai.</p>
@if($active)
<p>Anda masih memiliki sesi aktif. Lanjutkan sesi itu terlebih dahulu tanpa mengurangi kuota.</p><a class="button button-primary" href="{{ route('member.quick-quizzes.take',$active) }}">Lanjutkan sesi →</a>
@elseif($quotaToday['allowed'] && ($quotaToday['unlimited'] || $quotaToday['remaining']>0))
<form method="POST" action="{{ route('member.quick-quizzes.start',$quiz) }}">@csrf<input type="hidden" name="start_key" value="{{ $startKey }}"><button type="submit" class="button button-primary">Mulai dan gunakan 1 kuota →</button></form>
@else
<p>Belum dapat memulai: manfaat harian belum dikonfigurasi atau sisa kuota telah habis.</p>
@endif
</section>
@endsection
