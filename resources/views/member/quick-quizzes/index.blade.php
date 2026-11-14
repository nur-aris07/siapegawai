@extends('layouts.workspace')
@section('title','Quiz Kilat')
@section('content')
<div class="workspace-heading"><div><span class="workspace-label">LATIHAN CEPAT</span><h1>Quiz Kilat</h1><p>Quiz terbit dengan waktu per soal. Jawaban dikunci saat dipilih atau ketika waktunya habis.</p></div></div>
@include('components.form-errors')
@include('member.quick-quizzes.partials.quota')
<nav class="qq-analytics-page-links qq-analytics-entry-links" aria-label="Navigasi hasil Quiz Kilat">
    <a class="qq-analytics-action" href="{{ route('member.quick-quizzes.analytics') }}">Analisis Quiz Kilat →</a>
    <a class="qq-analytics-action qq-analytics-action--secondary" href="{{ route('member.quick-quizzes.history') }}">Lihat riwayat Quiz Kilat →</a>
</nav>
@if($active)
<section class="workspace-note qq-notice"><strong>Ada sesi yang dapat dilanjutkan</strong><p>{{ $active->version?->title }} · v{{ $active->version?->version_number }}. Membuka sesi yang sama tidak memotong kuota lagi.</p><a class="button button-primary" href="{{ route('member.quick-quizzes.take',$active) }}">Lanjutkan sesi →</a></section>
@endif
@if($quizzes->isEmpty())
<section class="workspace-note"><strong>Belum ada Quiz Kilat terbit</strong><p>Admin belum membuka quiz untuk peserta.</p></section>
@else
<div class="workspace-lines qq-list">
@foreach($quizzes as $quiz)
<article class="workspace-line"><div><span class="workspace-label">{{ $quiz->code }} · V{{ $quiz->currentVersion->version_number }}</span><h2>{{ $quiz->currentVersion->title }}</h2><p>{{ $quiz->currentVersion->description }}</p><p>{{ $quiz->currentVersion->items_count }} soal · {{ $quiz->currentVersion->seconds_per_question }} detik per soal</p></div><a class="text-link" href="{{ route('member.quick-quizzes.show',$quiz) }}">Lihat detail →</a></article>
@endforeach
</div>
{{ $quizzes->links() }}
@endif
<p class="member-disclaimer">Skor Quiz Kilat adalah persentase jawaban benar, bukan skor CAT resmi. Pemakaian kuota berlaku bersama untuk seluruh judul.</p>
@endsection
