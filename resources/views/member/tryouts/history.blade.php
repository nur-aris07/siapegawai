@extends('layouts.workspace')
@section('title','Riwayat dan progres tryout')
@section('content')
<div class="workspace-heading member-history-heading">
    <div>
        <span class="workspace-label">PERKEMBANGAN BELAJAR</span>
        <h1>Riwayat &amp; progres tryout</h1>
        <p>Hasil latihan anggota yang tersimpan di akunmu. Nilai dihitung dari versi soal yang digunakan pada percobaan tersebut.</p>
    </div>
    <a class="button button-outline" href="{{ route('member.tryouts.index') }}">Lihat tryout tersedia</a>
</div>

<section aria-labelledby="member-progress-title">
    <h2 id="member-progress-title" class="member-history-section-heading">Ringkasan hasil</h2>
    <div class="member-progress-metrics">
        <div><strong>{{ (int) ($metrics->sessions_count ?? 0) }}</strong><span>Percobaan selesai</span></div>
        <div><strong>{{ $metrics?->best_score !== null ? number_format((float) $metrics->best_score, 1, ',', '.') . '%' : '—' }}</strong><span>Skor terbaik</span></div>
        <div><strong>{{ $metrics?->average_score !== null ? number_format((float) $metrics->average_score, 1, ',', '.') . '%' : '—' }}</strong><span>Rata-rata skor</span></div>
        <div><strong>{{ (int) ($metrics->correct_total ?? 0) }}</strong><span>Total jawaban benar</span></div>
    </div>
</section>

@if($active->isNotEmpty())
<section aria-labelledby="member-active-title" class="member-history-section">
    <h2 id="member-active-title" class="member-history-section-heading">Sesi yang sedang berjalan</h2>
    <div class="workspace-lines">
        @foreach($active as $attempt)
        <article class="workspace-line member-history-line">
            <div>
                <h3>{{ $attempt->package?->name ?? 'Tryout' }} <span class="member-history-meta">· Percobaan {{ $attempt->attempt_number }}</span></h3>
                <p>Batas waktu {{ $attempt->expires_at?->format('d M Y H:i') }} · {{ $attempt->total_questions }} soal</p>
            </div>
            <a class="text-link" href="{{ route('member.tryouts.take', $attempt) }}">Lanjutkan sesi →</a>
        </article>
        @endforeach
    </div>
</section>
@endif

@include('member.tryouts.partials.subject-analysis', ['subjectAnalysis' => $subjectAnalysis, 'headingId' => 'member-history-subject-title', 'title' => 'Analisis hasil per materi'])

<section aria-labelledby="member-history-title" class="member-history-section">
    <div class="member-history-section-top">
        <h2 id="member-history-title" class="member-history-section-heading">Riwayat percobaan</h2>
        <span class="member-history-meta">{{ $history->total() }} hasil tersimpan</span>
    </div>
    @if($history->isEmpty())
    <div class="workspace-note">
        <strong>Belum ada hasil tryout</strong>
        <p>Setelah kamu mengumpulkan jawaban atau waktu pengerjaan habis, hasil akan tampil di sini.</p>
        <a class="text-link" href="{{ route('member.tryouts.index') }}">Pilih tryout →</a>
    </div>
    @else
    <div class="member-history-list">
        @foreach($history as $attempt)
        <article class="member-history-record">
            <div class="member-history-record-main">
                <span class="member-eyebrow">{{ $attempt->package?->code ?? 'TRYOUT' }} · Percobaan {{ $attempt->attempt_number }}</span>
                <h3>{{ $attempt->package?->name ?? 'Tryout' }}</h3>
                <p>{{ $attempt->finished_at?->format('d M Y H:i') }} · {{ $attempt->status === 'timed_out' ? 'Waktu habis' : 'Dikumpulkan' }}{{ $attempt->package?->status === 'archived' ? ' · Template diarsipkan' : '' }}</p>
            </div>
            <div class="member-history-result">
                <strong>{{ number_format((float) $attempt->score_percent, 1, ',', '.') }}%</strong>
                <span>{{ (int) $attempt->correct_count }} benar · {{ (int) $attempt->answered_count }}/{{ (int) $attempt->total_questions }} dijawab</span>
            </div>
            <a class="text-link" href="{{ route('member.tryouts.result', $attempt) }}">Lihat pembahasan →</a>
        </article>
        @endforeach
    </div>
    @if($history->hasPages())
    <nav class="member-history-pagination" aria-label="Halaman riwayat tryout">
        @if($history->onFirstPage()) <span class="member-history-muted">Sebelumnya</span>
        @else <a class="button button-outline button-compact" href="{{ $history->previousPageUrl() }}" rel="prev">← Sebelumnya</a> @endif
        <span>Halaman {{ $history->currentPage() }} dari {{ $history->lastPage() }}</span>
        @if($history->hasMorePages()) <a class="button button-outline button-compact" href="{{ $history->nextPageUrl() }}" rel="next">Berikutnya →</a>
        @else <span class="member-history-muted">Berikutnya</span> @endif
    </nav>
    @endif
    @endif
</section>

@if($hasMoreExpired)
<p class="member-disclaimer">Sebagian sesi lama masih diproses sebagai waktu habis. Buka kembali halaman ini untuk memperbarui ringkasan.</p>
@endif
<p class="member-disclaimer">Skor latihan menggunakan 1 poin per jawaban benar; bukan bobot SKD/TKP resmi. Data riwayat demo Guest tidak digabungkan dengan riwayat akun.</p>
@endsection
