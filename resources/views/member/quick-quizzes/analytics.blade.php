@extends('layouts.workspace')
@section('title', 'Analisis Quiz Kilat')
@section('content')
<div class="workspace-heading qq-analytics-heading">
    <div>
        <a class="task-back" href="{{ route('member.quick-quizzes.index') }}">← Quiz Kilat</a>
        <span class="workspace-label">PROGRES LATIHAN CEPAT</span>
        <h1>Analisis Quiz Kilat</h1>
        <p>Performa dari sesi Quiz Kilat yang <strong>sudah selesai</strong>. Angka dihitung dari jawaban yang terkunci server.</p>
    </div>
    <nav class="qq-analytics-page-links" aria-label="Navigasi analisis Quiz Kilat">
        <a class="qq-analytics-action" href="{{ route('member.quick-quizzes.history') }}">Lihat riwayat &amp; review →</a>
        <a class="qq-analytics-action qq-analytics-action--secondary" href="{{ route('member.quick-quizzes.index') }}">Kembali ke katalog</a>
    </nav>
</div>
@if($summary['sessions'] === 0)
<section class="workspace-note qq-analytics-empty" aria-label="Belum ada analisis">
    <strong>Belum ada sesi Quiz Kilat selesai</strong>
    <p>Mulai dan selesaikan satu Quiz Kilat untuk melihat visual ketepatan, tren skor, serta rincian kategori/topik. Sesi berjalan atau terhenti tidak dihitung sebagai hasil final.</p>
    <a class="qq-analytics-action" href="{{ route('member.quick-quizzes.index') }}">Pilih Quiz Kilat →</a>
</section>
@else
@php
    $lockedTotal = max(1, (int) $summary['locked']);
    $correctWidth = round(600 * (int) $summary['correct'] / $lockedTotal, 2);
    $wrongWidth = round(600 * (int) $summary['wrong'] / $lockedTotal, 2);
    $timeoutWidth = max(0, 600 - $correctWidth - $wrongWidth);
    $trendCount = $trend->count();
    $trendPoints = $trend->values()->map(function ($attempt, $index) use ($trendCount) {
        $x = $trendCount === 1 ? 334 : 44 + (580 * $index / ($trendCount - 1));
        $score = max(0, min(100, (float) $attempt->score_percent));
        return ['x' => round($x, 2), 'y' => round(142 - 1.12 * $score, 2), 'score' => $score];
    });
    $trendPolyline = $trendPoints->map(fn ($point) => $point['x'] . ',' . $point['y'])->implode(' ');
@endphp
<section aria-labelledby="qq-analytics-overview-heading" class="qq-analytics-section">
    <div class="qq-analytics-section-head">
        <div><span class="workspace-label">RINGKASAN PERFORMA</span><h2 id="qq-analytics-overview-heading">Gambaran Quiz Kilat</h2></div>
        <p>Hanya hasil final milik akun Anda</p>
    </div>
    <div class="qq-analytics-kpis">
        <div class="qq-analytics-kpi"><span>Sesi selesai</span><strong>{{ $summary['sessions'] }}</strong><small>percobaan selesai</small></div>
        <div class="qq-analytics-kpi"><span>Rata-rata skor</span><strong>{{ number_format($summary['average_score'], 1, ',', '.') }}%</strong><small>persentase skor sesi</small></div>
        <div class="qq-analytics-kpi"><span>Skor terbaik</span><strong>{{ number_format($summary['best_score'], 1, ',', '.') }}%</strong><small>dari seluruh sesi selesai</small></div>
        <div class="qq-analytics-kpi"><span>Rata-rata respons</span><strong>{{ $summary['average_response_ms'] === null ? '—' : number_format($summary['average_response_ms'] / 1000, 2, ',', '.') . ' dtk' }}</strong><small>soal dijawab, tanpa timeout</small></div>
    </div>
    <div class="qq-analytics-charts">
        <article class="qq-analytics-chart-card" aria-labelledby="qq-analytics-answer-heading">
            <div class="qq-analytics-chart-head"><h3 id="qq-analytics-answer-heading">Distribusi jawaban terkunci</h3><p>{{ $summary['locked'] }} soal · akurasi {{ $summary['accuracy'] === null ? '—' : number_format($summary['accuracy'], 1, ',', '.') . '%' }}</p></div>
            <svg class="qq-analytics-mix-graph" viewBox="0 0 600 30" role="img" aria-label="{{ $summary['correct'] }} benar, {{ $summary['wrong'] }} salah, {{ $summary['timeout'] }} waktu habis dari {{ $summary['locked'] }} soal terkunci">
                <title>Distribusi benar, salah dan waktu habis</title>
                <rect class="qq-mix-bg" x="0" y="2" width="600" height="26" rx="5" />
                <rect class="qq-mix-correct" x="0" y="2" width="{{ $correctWidth }}" height="26" />
                <rect class="qq-mix-wrong" x="{{ $correctWidth }}" y="2" width="{{ $wrongWidth }}" height="26" />
                <rect class="qq-mix-timeout" x="{{ $correctWidth + $wrongWidth }}" y="2" width="{{ $timeoutWidth }}" height="26" />
            </svg>
            <dl class="qq-analytics-legend">
                <div><dt><span class="qq-legend-dot qq-legend-correct"></span>Benar</dt><dd>{{ $summary['correct'] }}</dd></div>
                <div><dt><span class="qq-legend-dot qq-legend-wrong"></span>Salah</dt><dd>{{ $summary['wrong'] }}</dd></div>
                <div><dt><span class="qq-legend-dot qq-legend-timeout"></span>Waktu habis</dt><dd>{{ $summary['timeout'] }}</dd></div>
            </dl>
            <p class="qq-analytics-helper">Akurasi = jawaban benar ÷ seluruh soal terkunci, termasuk timeout. Bukan skor CAT.</p>
        </article>
        <article class="qq-analytics-chart-card" aria-labelledby="qq-analytics-trend-heading">
            <div class="qq-analytics-chart-head"><h3 id="qq-analytics-trend-heading">Tren 8 sesi selesai terakhir</h3><p>Skor per sesi · urut waktu</p></div>
            <svg class="qq-analytics-line-graph" viewBox="0 0 660 182" role="img" aria-label="Tren skor {{ $trendCount }} sesi terakhir, dari {{ number_format((float) $trend->first()->score_percent, 1, ',', '.') }} persen hingga {{ number_format((float) $trend->last()->score_percent, 1, ',', '.') }} persen">
                <title>Tren skor sesi Quiz Kilat yang selesai</title>
                <line class="qq-grid-line" x1="44" y1="30" x2="624" y2="30" />
                <line class="qq-grid-line" x1="44" y1="86" x2="624" y2="86" />
                <line class="qq-grid-line" x1="44" y1="142" x2="624" y2="142" />
                <text class="qq-chart-axis" x="8" y="34">100%</text>
                <text class="qq-chart-axis" x="15" y="90">50%</text>
                <text class="qq-chart-axis" x="20" y="146">0%</text>
                @if($trendCount > 1)
                <polyline class="qq-line-series" points="{{ $trendPolyline }}" />
                @endif
                @foreach($trendPoints as $index => $point)
                    <circle class="qq-line-point" cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="5">
                        <title>Sesi {{ $index + 1 }}: {{ number_format($point['score'], 1, ',', '.') }}%</title>
                    </circle>
                @endforeach
            </svg>
            <div class="qq-analytics-trend-ends"><span>Lebih lama</span><span>Terbaru</span></div>
            <details class="qq-analytics-mini-details">
                <summary>Lihat angka setiap sesi ({{ $trendCount }})</summary>
                <ol class="qq-analytics-trend-list">
                @foreach($trend as $attempt)
                    <li><span>{{ $attempt->title }} · {{ $attempt->finished_at ? \Illuminate\Support\Carbon::parse($attempt->finished_at)->timezone('Asia/Jakarta')->format('d M Y H:i') . ' WIB' : 'Waktu tidak tersedia' }}</span><strong>{{ number_format((float) $attempt->score_percent, 1, ',', '.') }}%</strong></li>
                @endforeach
                </ol>
            </details>
        </article>
    </div>
</section>
<section class="qq-analytics-section" aria-labelledby="qq-analytics-category-overview-heading">
    <div class="qq-analytics-section-head"><div><span class="workspace-label">PETA KETEPATAN</span><h2 id="qq-analytics-category-overview-heading">Ketepatan menurut kategori</h2></div></div>
    <div class="qq-analytics-category-overview">
        @foreach($subjects->sortByDesc('total')->take(4) as $subject)
            @php($percentage = 100 * (int) $subject->correct / max(1, (int) $subject->total))
            <div class="qq-analytics-category-row">
                <div class="qq-analytics-category-label"><strong>{{ $subject->subject_name_snapshot ?: ($subject->subject_code_snapshot ?: 'Tanpa kategori') }}</strong><span>{{ $subject->correct }}/{{ $subject->total }} benar · {{ number_format($percentage, 1, ',', '.') }}%</span></div>
                <progress max="100" value="{{ $percentage }}" aria-label="Ketepatan {{ $subject->subject_name_snapshot ?: 'kategori' }}">{{ number_format($percentage, 1, ',', '.') }}%</progress>
            </div>
        @endforeach
    </div>
    <p class="qq-analytics-helper">Kategori dan topik memakai snapshot saat quiz terbit. Buka rincian untuk melihat semua nilai dan mengurutkannya.</p>
</section>
<section class="qq-analytics-section qq-analytics-details" aria-labelledby="qq-analytics-details-heading">
    <div class="qq-analytics-section-head"><div><span class="workspace-label">DATA RINCI</span><h2 id="qq-analytics-details-heading">Rincian kategori &amp; topik</h2></div></div>
    <p class="qq-analytics-intro">Tabel berikut sengaja diringkas. Buka kategori/topik sesuai kebutuhan, lalu cari, urutkan dengan header, atau pindah halaman.</p>
    <details class="qq-analytics-disclosure">
        <summary><span>Menurut kategori saat terbit</span><small>{{ $subjects->count() }} kategori</small></summary>
        <div class="qq-analytics-data" data-qq-analytics-grid>
            <div class="qq-analytics-controls" hidden>
                <label>Cari kategori <input type="search" data-qq-search placeholder="Ketik nama kategori" autocomplete="off"></label>
                <label>Baris per halaman <select data-qq-page-size><option value="5">5</option><option value="10">10</option><option value="25">25</option></select></label>
            </div>
            <div class="qq-analytics-table-wrap"><table class="qq-analytics-table" data-qq-analytics-table id="qq-analytics-subjects">
                <caption class="sr-only">Rincian akurasi untuk setiap kategori saat publikasi Quiz Kilat</caption>
                <thead><tr><th scope="col">Kategori</th><th scope="col" data-qq-numeric>Benar</th><th scope="col" data-qq-numeric>Salah</th><th scope="col" data-qq-numeric>Timeout</th><th scope="col" data-qq-numeric>Ketepatan</th></tr></thead>
                <tbody>@foreach($subjects as $subject)
                    <tr><th scope="row">{{ $subject->subject_name_snapshot ?: ($subject->subject_code_snapshot ?: 'Tanpa kategori') }} @if($subject->subject_name_snapshot && $subject->subject_code_snapshot)<span class="qq-analytics-muted">({{ $subject->subject_code_snapshot }})</span>@endif</th>
                        <td data-order="{{ $subject->correct }}">{{ $subject->correct }}</td><td data-order="{{ $subject->wrong }}">{{ $subject->wrong }}</td><td data-order="{{ $subject->timeout }}">{{ $subject->timeout }}</td>
                        <td data-order="{{ 100 * (int) $subject->correct / max(1, (int) $subject->total) }}">{{ number_format(100 * (int) $subject->correct / max(1, (int) $subject->total), 1, ',', '.') }}%</td></tr>
                @endforeach</tbody>
            </table></div>
            <div class="qq-analytics-data-footer" hidden><output data-qq-page-status aria-live="polite"></output><div class="qq-analytics-pager"><button type="button" data-qq-prev>← Sebelumnya</button><button type="button" data-qq-next>Berikutnya →</button></div></div>
        </div>
    </details>
    <details class="qq-analytics-disclosure">
        <summary><span>Menurut topik saat terbit</span><small>{{ $topics->count() }} topik</small></summary>
        <div class="qq-analytics-data" data-qq-analytics-grid>
            <div class="qq-analytics-controls" hidden>
                <label>Cari topik <input type="search" data-qq-search placeholder="Ketik kategori atau topik" autocomplete="off"></label>
                <label>Baris per halaman <select data-qq-page-size><option value="5">5</option><option value="10">10</option><option value="25">25</option></select></label>
            </div>
            <div class="qq-analytics-table-wrap"><table class="qq-analytics-table" data-qq-analytics-table id="qq-analytics-topics">
                <caption class="sr-only">Rincian akurasi untuk setiap topik berdasarkan snapshot saat publikasi</caption>
                <thead><tr><th scope="col">Kategori · topik</th><th scope="col" data-qq-numeric>Benar</th><th scope="col" data-qq-numeric>Salah</th><th scope="col" data-qq-numeric>Timeout</th><th scope="col" data-qq-numeric>Ketepatan</th></tr></thead>
                <tbody>@foreach($topics as $topic)
                    <tr><th scope="row"><span class="qq-analytics-muted">{{ $topic->subject_code_snapshot ?: '—' }} · </span>{{ $topic->topic_name_snapshot ?: 'Tanpa topik' }}</th>
                        <td data-order="{{ $topic->correct }}">{{ $topic->correct }}</td><td data-order="{{ $topic->wrong }}">{{ $topic->wrong }}</td><td data-order="{{ $topic->timeout }}">{{ $topic->timeout }}</td>
                        <td data-order="{{ 100 * (int) $topic->correct / max(1, (int) $topic->total) }}">{{ number_format(100 * (int) $topic->correct / max(1, (int) $topic->total), 1, ',', '.') }}%</td></tr>
                @endforeach</tbody>
            </table></div>
            <div class="qq-analytics-data-footer" hidden><output data-qq-page-status aria-live="polite"></output><div class="qq-analytics-pager"><button type="button" data-qq-prev>← Sebelumnya</button><button type="button" data-qq-next>Berikutnya →</button></div></div>
        </div>
    </details>
</section>
@endif
<p class="member-disclaimer qq-analytics-disclaimer">Analisis hanya memakai Quiz Kilat yang selesai dan snapshot kategori/topik saat publikasi. Tidak dicampur dengan skor CAT atau Latihan Kelas dan bukan nilai resmi CPNS.</p>
@vite('resources/js/member-qq-analytics.js')
@endsection
