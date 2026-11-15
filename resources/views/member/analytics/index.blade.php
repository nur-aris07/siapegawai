@extends('layouts.workspace')
@section('title', 'Analisis Kemampuan')
@section('content')
<div class="domain-hub" id="domain-hub">
    <div class="domain-hub-intro">
        <span class="workspace-label">ANALISIS BELAJAR</span>
        <h1>Analisis Kemampuan</h1>
        <p>Hasil latihan dari CAT, Kelas, dan Quiz Kilat ditampilkan <strong>terpisah</strong>. Ini data latihan akun Anda, bukan nilai CAT resmi atau prediksi kelulusan.</p>
    </div>

    <section class="domain-hub-section" id="domain-cat" aria-labelledby="domain-cat-title">
        <div class="domain-hub-head"><div><span class="workspace-label">TRYOUT ANGGOTA</span><h2 id="domain-cat-title">CAT / Tryout</h2><p>Skor latihan internal dari sesi selesai dan waktu habis; paket yang berbeda belum tentu memiliki tingkat kesulitan setara.</p></div><a class="domain-hub-link" href="{{ route('member.tryouts.history') }}">Riwayat &amp; analisis Tryout →</a></div>
        @if($cat['sessions'] === 0)
            <div class="domain-hub-empty"><strong>Belum ada hasil Tryout final</strong><p>Mulai dan selesaikan Tryout untuk melihat grafik skor latihan.</p><a href="{{ route('member.tryouts.index') }}">Buka Tryout saya →</a></div>
        @else
            <div class="domain-hub-grid">
                <dl class="domain-hub-metrics">
                    <div><dt>Sesi final</dt><dd>{{ $cat['sessions'] }}<small>selesai / waktu habis</small></dd></div>
                    <div><dt>Rata-rata skor latihan</dt><dd>{{ number_format($cat['average_score'], 1, ',', '.') }}%<small>rata-rata sesi final</small></dd></div>
                    <div><dt>Skor terbaik</dt><dd>{{ number_format($cat['best_score'], 1, ',', '.') }}%<small>bukan skor CAT resmi</small></dd></div>
                    <div><dt>Sesi pada grafik</dt><dd>{{ count($cat['points']) }}<small>maksimal 5 terbaru</small></dd></div>
                </dl>
                <figure class="domain-hub-figure">
                    <figcaption><strong>Hasil {{ count($cat['points']) }} Tryout terakhir</strong><span>Skor per sesi, dari lama ke terbaru</span></figcaption>
                    <svg viewBox="0 0 430 164" role="img" aria-label="Grafik {{ count($cat['points']) }} sesi Tryout. Angka setiap sesi tersedia di bawah grafik.">
                        <title>Grafik hasil Tryout terakhir</title>
                        <line class="domain-hub-gridline" x1="36" y1="20" x2="410" y2="20"/>
                        <line class="domain-hub-gridline" x1="36" y1="77.5" x2="410" y2="77.5"/>
                        <line class="domain-hub-gridline" x1="36" y1="135" x2="410" y2="135"/>
                        <text class="domain-hub-axis" x="0" y="24">100%</text><text class="domain-hub-axis" x="9" y="81">50%</text><text class="domain-hub-axis" x="17" y="139">0%</text>
                        @if(count($cat['points']) > 1)<polyline class="domain-hub-scoreline" points="{{ $cat['polyline'] }}"/>@endif
                        @foreach($cat['points'] as $index => $point)
                            <circle class="domain-hub-scorepoint" cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="5"><title>Sesi {{ $index + 1 }}: {{ number_format($point['score'], 1, ',', '.') }}%</title></circle>
                            <text class="domain-hub-axis" x="{{ $point['x'] }}" y="154" text-anchor="middle">{{ $index + 1 }}</text>
                        @endforeach
                    </svg>
                    <p class="domain-hub-chart-foot">@foreach($cat['points'] as $index => $point)Sesi {{ $index + 1 }}: {{ number_format($point['score'], 1, ',', '.') }}%{{ !$loop->last ? ' · ' : '' }}@endforeach</p>
                </figure>
            </div>
        @endif
    </section>

    <section class="domain-hub-section" id="domain-class" aria-labelledby="domain-class-title">
        <div class="domain-hub-head"><div><span class="workspace-label">BELAJAR ANGGOTA</span><h2 id="domain-class-title">Latihan Kelas</h2><p>Materi yang ditandai selesai mengukur progres belajar, <strong>bukan penguasaan materi</strong>. Hasil latihan soal dihitung terpisah.</p></div><a class="domain-hub-link" href="{{ route('member.classes.index') }}">Buka Kelas saya →</a></div>
        @if($classes['count'] === 0)
            <div class="domain-hub-empty"><strong>Belum ada kelas dengan akses aktif</strong><p>Hanya kelas yang masih dapat diakses yang tampil di sini.</p><a href="{{ route('member.classes.index') }}">Lihat Kelas saya →</a></div>
        @else
            <div class="domain-hub-grid">
                <dl class="domain-hub-metrics domain-hub-metrics--compact">
                    <div><dt>Kelas aktif</dt><dd>{{ $classes['count'] }}<small>enrollment aktif</small></dd></div>
                    <div><dt>Latihan Kelas final</dt><dd>{{ $classes['practice_sessions'] }}<small>hanya pada kelas aktif</small></dd></div>
                </dl>
                <div class="domain-hub-figure" aria-label="Progres materi kelas aktif">
                    <h3>Progres materi kelas</h3><p class="domain-hub-caption">Materi terbit yang sudah ditandai selesai; maksimal tiga kelas terbaru</p>
                    @foreach($classes['previews'] as $class)
                        <div class="domain-hub-class-row"><div class="domain-hub-class-meta"><a href="{{ route('member.classes.show', $class['class_id']) }}">{{ $class['title'] }}</a><span>{{ $class['total'] > 0 ? $class['completed'].' / '.$class['total'].' materi' : 'Belum ada materi terbit' }}</span></div>
                        @if($class['total'] > 0)<progress value="{{ $class['completed'] }}" max="{{ $class['total'] }}">{{ $class['percent'] }}%</progress>@endif</div>
                    @endforeach
                    @if($classes['more'] > 0)<p class="domain-hub-caption">{{ $classes['more'] }} kelas aktif lain dapat dibuka melalui Kelas saya.</p>@endif
                </div>
            </div>
        @endif
    </section>

    <section class="domain-hub-section" id="domain-qq" aria-labelledby="domain-qq-title">
        <div class="domain-hub-head"><div><span class="workspace-label">LATIHAN CEPAT</span><h2 id="domain-qq-title">Quiz Kilat</h2><p>Data hanya dari sesi selesai dengan jawaban yang sudah dikunci server. Tidak digabungkan dengan skor Tryout.</p></div><a class="domain-hub-link" href="{{ route('member.quick-quizzes.analytics') }}">Analisis Quiz Kilat →</a></div>
        @if($qq['sessions'] === 0)
            <div class="domain-hub-empty"><strong>Belum ada sesi Quiz Kilat selesai</strong><p>Sesi aktif atau ditinggalkan tidak menjadi hasil final.</p><a href="{{ route('member.quick-quizzes.index') }}">Buka Quiz Kilat →</a></div>
        @else
            <div class="domain-hub-grid">
                <dl class="domain-hub-metrics">
                    <div><dt>Sesi selesai</dt><dd>{{ $qq['sessions'] }}<small>hanya completed</small></dd></div>
                    <div><dt>Ketepatan terkunci</dt><dd>{{ $qq['accuracy'] === null ? '—' : number_format($qq['accuracy'], 1, ',', '.') . '%' }}<small>{{ $qq['correct'] }} benar dari {{ $qq['locked'] }} terkunci</small></dd></div>
                    <div><dt>Rata-rata respons</dt><dd>{{ $qq['average_response_ms'] === null ? '—' : number_format($qq['average_response_ms'] / 1000, 2, ',', '.') . ' dtk' }}<small>hanya soal dijawab</small></dd></div>
                    <div><dt>Soal terkunci</dt><dd>{{ $qq['locked'] }}<small>bukan jumlah soal unik</small></dd></div>
                </dl>
                <figure class="domain-hub-figure"><figcaption><strong>Distribusi jawaban terkunci</strong><span>Benar, salah, waktu habis dari {{ $qq['locked'] }} soal</span></figcaption>
                    @if($qq['locked'] > 0)
                    <svg viewBox="0 0 400 34" role="img" aria-label="{{ $qq['correct'] }} benar, {{ $qq['wrong'] }} salah, {{ $qq['timeout'] }} waktu habis">
                        <title>Distribusi jawaban Quiz Kilat</title>
                        <rect class="domain-hub-mix-bg" x="0" y="5" width="400" height="23" rx="2"/>
                        <rect class="domain-hub-mix-correct" x="0" y="5" width="{{ $qq['bar']['correct'] }}" height="23"/>
                        <rect class="domain-hub-mix-wrong" x="{{ $qq['bar']['wrong_x'] }}" y="5" width="{{ $qq['bar']['wrong'] }}" height="23"/>
                        <rect class="domain-hub-mix-timeout" x="{{ $qq['bar']['timeout_x'] }}" y="5" width="{{ $qq['bar']['timeout'] }}" height="23"/>
                    </svg>
                    @else <p class="domain-hub-caption">Tidak ada soal terkunci yang dapat dihitung.</p> @endif
                    <div class="domain-hub-legend"><span><i class="domain-hub-swatch domain-hub-swatch--correct" aria-hidden="true"></i>Benar <strong>{{ $qq['correct'] }}</strong></span><span><i class="domain-hub-swatch domain-hub-swatch--wrong" aria-hidden="true"></i>Salah <strong>{{ $qq['wrong'] }}</strong></span><span><i class="domain-hub-swatch domain-hub-swatch--timeout" aria-hidden="true"></i>Waktu habis <strong>{{ $qq['timeout'] }}</strong></span></div>
                    <p class="domain-hub-chart-foot">Ketepatan = benar ÷ seluruh soal terkunci (termasuk timeout). Rata-rata respons tidak mencakup timeout.</p>
                </figure>
            </div>
        @endif
    </section>

    <details class="domain-hub-method"><summary>Cara membaca hasil dan batas interpretasinya</summary><p>CAT menggunakan persentase latihan internal sesi final. Kelas menampilkan materi terbit yang ditandai selesai dan jumlah latihan final secara terpisah. Quiz Kilat menghitung benar dari seluruh soal terkunci, termasuk timeout. Jumlah soal dapat mencakup pengulangan, bukan soal unik. Data minim bukan bukti bahwa kompetensi sudah atau belum dikuasai.</p></details>
</div>
@endsection
