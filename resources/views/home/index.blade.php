@extends('layouts.public')
@section('title','Beranda')
@section('content')
<section class="hero">
    <div class="container hero-grid">
        <div class="hero-copy"><span class="eyebrow">PERSIAPAN SELEKSI CPNS</span>
            <h1>Belajar CPNS,<br> dengan arah yang <em>jelas.</em></h1>
            <p class="hero-sub">Latih kemampuan SKD, pahami alasan di balik setiap jawaban, lalu tentukan materi yang
                perlu kamu perkuat. Semua dimulai dari satu latihan kecil.</p>
            <div class="hero-actions"><a href="{{ route('practice.intro') }}" class="button button-primary">Coba latihan
                    SKD <svg class="icon" aria-hidden="true">
                        <use href="#ic-arrow-right"></use>
                    </svg></a><a class="text-link" href="{{ route('questions.index') }}">Jelajahi bank soal <svg
                        class="icon" aria-hidden="true">
                        <use href="#ic-arrow-right"></use>
                    </svg></a></div>
            <div class="hero-points"><span><svg class="icon" aria-hidden="true">
                        <use href="#ic-check"></use>
                    </svg> Tanpa akun untuk mencoba</span><span><svg class="icon" aria-hidden="true">
                        <use href="#ic-check"></use>
                    </svg> Ada pembahasan</span><span><svg class="icon" aria-hidden="true">
                        <use href="#ic-check"></use>
                    </svg> Bisa dari HP</span></div>
        </div>
        <div class="hero-visual">
            <div class="visual-top">KENALAN DENGAN SIAPEGAWAI</div>
            <div class="visual-index">01.</div><img src="{{ asset('assets/brand/group-khaki.webp') }}" width="1160"
                height="689" alt="Nara, Bima, dan Laras dengan seragam khaki membawa laptop, tablet, dan buku"
                fetchpriority="high">
            <div class="visual-bottom"><strong>Mulai belajar dari hal yang paling penting.</strong><span>SKD · SKB ·
                    EVALUASI</span></div>
        </div>
    </div>
</section>
<section class="manifesto">
    <div class="container manifesto-inner">
        <div class="manifesto-title">BISA DICOBA SEKARANG</div>
        <div class="manifesto-item"><b>9 soal SKD</b>
            <p>Satu sesi latihan dengan waktu 12 menit.</p>
        </div>
        <div class="manifesto-item"><b>12 contoh soal</b>
            <p>TWK, TIU, TKP, dan contoh SKB.</p>
        </div>
        <div class="manifesto-item"><b>Hasil demo sementara</b>
            <p>Pembahasan setelah pengerjaan, tanpa akun.</p>
        </div>
    </div>
</section>
<section class="section">
    <div class="container">
        <div class="section-top">
            <div><span class="eyebrow">CARA BELAJAR</span>
                <h2>Tiga langkah, satu tujuan:<br> lebih siap menghadapi tes.</h2>
            </div>
            <p>Kami menempatkan pengalaman latihan lebih dulu, sebelum kamu memutuskan perlu ikut kelas atau tidak.</p>
        </div>
        <div class="route-steps">
            <article class="route-step"><span class="route-step-num">01 / COBA</span>
                <h3>Ukur kemampuan awal.</h3>
                <p>Mulai dari latihan singkat TWK, TIU, dan TKP. Tidak perlu membuat akun atau memilih paket.</p><a
                    class="text-link" href="{{ route('practice.intro') }}">Mulai latihan <svg class="icon"
                        aria-hidden="true">
                        <use href="#ic-arrow-right"></use>
                    </svg></a>
            </article>
            <article class="route-step"><span class="route-step-num">02 / PAHAMI</span>
                <h3>Bedah jawabanmu.</h3>
                <p>Lihat hasil per materi, baca alasan setiap jawaban, lalu lanjutkan ke bank soal.</p><a
                    class="text-link" href="{{ route('questions.index') }}">Buka bank soal <svg class="icon"
                        aria-hidden="true">
                        <use href="#ic-arrow-right"></use>
                    </svg></a>
            </article>
            <article class="route-step"><span class="route-step-num">03 / TINGKATKAN</span>
                <h3>Perkuat yang belum mantap.</h3>
                <p>Pelajari rencana kelas SKD dan SKB, agar waktu belajarmu lebih terfokus.</p><a class="text-link"
                    href="{{ route('page.kelas') }}">Lihat kelas <svg class="icon" aria-hidden="true">
                        <use href="#ic-arrow-right"></use>
                    </svg></a>
            </article>
        </div>
    </div>
</section>
<section class="section section-cream">
    <div class="container preview-split">
        <div class="preview-copy"><span class="eyebrow">DI DALAM LATIHAN</span>
            <h2>Tidak cuma menjawab. Kamu perlu tahu kenapa.</h2>
            <p>Ruang latihan dirancang sederhana: satu soal dalam satu waktu, navigasi nomor yang jelas, serta
                pembahasan setelah selesai.</p>
            <ul class="evidence-list">
                <li><svg class="icon" aria-hidden="true">
                        <use href="#ic-check"></use>
                    </svg> Timer dan navigasi soal</li>
                <li><svg class="icon" aria-hidden="true">
                        <use href="#ic-check"></use>
                    </svg> Tandai soal yang ingin ditinjau</li>
                <li><svg class="icon" aria-hidden="true">
                        <use href="#ic-check"></use>
                    </svg> Evaluasi TWK, TIU, dan TKP</li>
            </ul><a href="{{ route('practice.intro') }}" class="button button-primary">Masuk ke latihan <svg
                    class="icon" aria-hidden="true">
                    <use href="#ic-arrow-right"></use>
                </svg></a>
            <p class="tiny muted prototype-disclaimer">Latihan ini contoh produk, bukan replika CAT BKN.</p>
        </div>
        <div class="question-sample" aria-label="Cuplikan tampilan soal latihan">
            <div class="sample-head"><strong>LATIHAN SKD / TWK</strong><span>Pratinjau soal</span></div>
            <div class="sample-body">
                <div class="sample-label">SOAL 01 DARI 09</div>
                <h3>Apa kedudukan Pancasila dalam sistem ketatanegaraan Indonesia?</h3>
                <div class="sample-option"><span>A.</span>
                    <div>Dasar negara dan pandangan hidup bangsa</div>
                </div>
                <div class="sample-option"><span>B.</span>
                    <div>Pedoman teknis penyusunan APBD</div>
                </div>
                <div class="sample-option"><span>C.</span>
                    <div>Aturan pelaksanaan satu kementerian</div>
                </div>
                <div class="sample-option"><span>D.</span>
                    <div>Peraturan daerah tingkat provinsi</div>
                </div>
            </div>
            <div class="sample-footer">
                <details>
                    <summary>Lihat kunci dan pembahasan contoh</summary>
                    <p><strong>A. Dasar negara dan pandangan hidup bangsa.</strong> Soal contoh untuk mengenali peran
                        Pancasila.</p>
                </details>
            </div>
        </div>
    </div>
</section>
<section class="section">
    <div class="container">
        <div class="section-top">
            <div><span class="eyebrow">JALUR BELAJAR</span>
                <h2>Pilih materi sesuai<br> tahapan seleksimu.</h2>
            </div>
            <p>Belum tentu semua orang butuh materi yang sama. Mulai dari tahapan yang sedang kamu persiapkan.</p>
        </div>
        <div class="line-courses">
            <article class="line-course">
                <div class="course-code">SKD</div>
                <div class="course-name">
                    <h3>Seleksi Kompetensi Dasar</h3>
                    <p>TWK, TIU, dan TKP dengan materi terstruktur.</p>
                </div>
                <div class="course-details"><b>Rencana pendampingan SKD</b> Silabus dan jadwal masih disusun.</div><a
                    class="text-link" href="{{ route('page.kelas') }}">Detail kelas <svg class="icon"
                        aria-hidden="true">
                        <use href="#ic-arrow-right"></use>
                    </svg></a>
            </article>
            <article class="line-course">
                <div class="course-code">SKB</div>
                <div class="course-name">
                    <h3>Seleksi Kompetensi Bidang</h3>
                    <p>Pendalaman sesuai kebutuhan jabatan/formasi.</p>
                </div>
                <div class="course-details"><b>Rencana pendampingan SKB</b> Materi akan dipetakan per formasi.</div><a
                    class="text-link" href="{{ route('page.kelas') }}">Detail kelas <svg class="icon"
                        aria-hidden="true">
                        <use href="#ic-arrow-right"></use>
                    </svg></a>
            </article>
        </div>
    </div>
</section>
<section class="brand-story">
    <div class="container story-grid">
        <div class="story-content"><span class="eyebrow eyebrow-light">KENALAN DENGAN KARAKTER KAMI</span>
            <h2>Nara, Bima, dan Laras. Teman di perjalanan belajarmu.</h2>
            <p>Tiga karakter asli Siapegawai hadir di berbagai seragam ASN. Identitas visualnya konsisten, sama seperti
                pengalaman belajar yang ingin kami bangun.</p><a href="{{ route('page.kelas') }}"
                class="text-link">Jelajahi kelas <svg class="icon" aria-hidden="true">
                    <use href="#ic-arrow-right"></use>
                </svg></a>
        </div>
        <div class="story-image"><img src="{{ asset('assets/brand/group-korpri.webp') }}" width="1160" height="689"
                loading="lazy" alt="Nara, Bima, dan Laras dalam seragam batik Korpri biru"></div>
    </div>
</section>
<section class="start-strip">
    <div class="container start-inner">
        <div>
            <h2>Mulai saja dari sembilan soal.</h2>
            <p>Belum perlu paket. Belum perlu komitmen panjang.</p>
        </div><a href="{{ route('practice.intro') }}" class="button button-primary">Coba sekarang <svg class="icon"
                aria-hidden="true">
                <use href="#ic-arrow-right"></use>
            </svg></a>
    </div>
</section>
@endsection