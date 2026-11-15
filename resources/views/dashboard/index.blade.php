@extends('layouts.workspace')
@section('title','Dashboard Peserta')
@section('content')
<div class="workspace-heading">
    <div>
        <span class="workspace-label">RINGKASAN PESERTA</span>
        <h1>Selamat datang, {{ $user->name }}</h1>
        <p>Akunmu aktif. Pilih kegiatan belajar yang saat ini tersedia.</p>
    </div>
    <span class="state-tag state-active">Akun aktif</span>
</div>
<section class="participant-home-section" aria-labelledby="participant-learning-title">
    <span class="workspace-label">LANGKAH BERIKUTNYA</span>
    <h2 id="participant-learning-title">Lanjut belajar</h2>
    <p class="participant-home-lead">Pilih aktivitas yang tersedia. Riwayat dan analisis dapat dibuka melalui domain masing-masing.</p>
    <div class="participant-home-tasks">
        <article class="participant-home-task">
            <span class="workspace-label">01 · UJIAN LATIHAN</span>
            <h3><svg class="participant-task-icon" aria-hidden="true"><use href="#ic-clipboard"/></svg>Tryout saya</h3>
            <p>{{ $availableTryouts }} template diterbitkan · {{ $memberAttempts }} percobaan tersimpan. Mulai, lanjutkan, atau periksa hasil.</p>
            <a class="text-link" href="{{ route('member.tryouts.index') }}">Buka Tryout →</a>
        </article>
        <article class="participant-home-task">
            <span class="workspace-label">02 · PEMBELAJARAN</span>
            <h3><svg class="participant-task-icon" aria-hidden="true"><use href="#ic-book"/></svg>Kelas saya</h3>
            <p>Buka materi dan latihan kelas yang aksesnya sudah diberikan. Lanjutkan progres tersimpan.</p>
            <a class="text-link" href="{{ route('member.classes.index') }}">Buka Kelas →</a>
        </article>
        <article class="participant-home-task">
            <span class="workspace-label">03 · LATIHAN CEPAT</span>
            <h3><svg class="participant-task-icon" aria-hidden="true"><use href="#ic-clock"/></svg>Quiz Kilat</h3>
            <p>Kerjakan soal singkat dengan waktu per soal dan tinjau hasil sesi yang sudah selesai.</p>
            <a class="text-link" href="{{ route('member.quick-quizzes.index') }}">Buka Quiz Kilat →</a>
        </article>
    </div>
</section>
<section class="participant-home-section participant-home-analysis" aria-labelledby="participant-analysis-title">
    <div>
        <span class="workspace-label">REFLEKSI BELAJAR</span>
        <h2 id="participant-analysis-title"><svg class="participant-task-icon" aria-hidden="true"><use href="#ic-chart-bar"/></svg>Analisis Kemampuan</h2>
        <p>Periksa tren Tryout, progres materi Kelas, dan ketepatan Quiz Kilat secara terpisah. Hasilnya bukan satu skor gabungan atau nilai CAT resmi.</p>
        <a class="text-link" href="{{ route('member.analytics.index') }}">Buka Analisis Kemampuan →</a>
    </div>
    <div class="participant-home-analysis-info" aria-label="Tiga jenis analisis">
        <p><strong>CAT / Tryout</strong> <span>Tren hasil sesi final</span></p>
        <p><strong>Latihan Kelas</strong> <span>Progres materi dan hasil latihan</span></p>
        <p><strong>Quiz Kilat</strong> <span>Ketepatan dan waktu respons</span></p>
    </div>
</section>
<section class="participant-home-public" aria-labelledby="participant-public-title">
    <h2 id="participant-public-title">Jelajahi konten publik</h2>
    <p>Contoh latihan dan informasi kelas tetap dapat dibaca tanpa memenuhi navigasi utama peserta.</p>
    <nav class="participant-home-public-links" aria-label="Konten publik">
        <a href="{{ route('practice.intro') }}">Demo SKD →</a>
        <a href="{{ route('questions.index') }}">Bank soal contoh →</a>
        <a href="{{ route('page.kelas') }}">Katalog kelas →</a>
    </nav>
</section>
@endsection
