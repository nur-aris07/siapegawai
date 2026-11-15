@extends('layouts.workspace')
@section('title', $learningClass->title)
@section('content')
<div class="class-course">
    <header class="class-course-header">
        <div>
            <span class="workspace-label">RUANG BELAJAR</span>
            <h1>{{ $learningClass->title }}</h1>
            <p>{{ $learningClass->description }}</p>
        </div>
        <a class="class-course-text-link" href="{{ route('member.classes.index') }}">
            <svg class="class-course-icon" aria-hidden="true"><use href="#ic-chevron-left"/></svg>
            Semua kelas
        </a>
    </header>

    <div class="class-course-layout">
            <section class="class-course-progress" aria-labelledby="class-progress-title">
                <div class="class-course-heading-inline">
                    <svg class="class-course-icon" aria-hidden="true"><use href="#ic-chart-bar"/></svg>
                    <h2 id="class-progress-title">Progres belajar</h2>
                </div>
                <strong class="class-course-progress-number">{{ $progress['completed'] }} dari {{ $progress['total'] }} materi selesai</strong>
                @if($progress['total'] > 0)
                    <progress value="{{ $progress['completed'] }}" max="{{ $progress['total'] }}">{{ $progress['percent'] }}%</progress>
                    <p><strong>{{ $progress['percent'] }}% selesai</strong> · berdasarkan materi yang ditandai selesai, bukan nilai ujian.</p>
                @else
                    <p>Belum ada materi diterbitkan.</p>
                @endif
            </section>

        <section class="class-course-materials" id="daftar-materi" aria-labelledby="class-materials-title">
            <div class="class-course-materials-heading">
                <div>
                    <h2 id="class-materials-title">Materi belajar</h2>
                    <p>Pilih judul materi untuk membaca. Hanya satu materi perlu dibuka pada satu waktu.</p>
                </div>
                <span class="class-course-material-count">{{ $materials->count() }} materi</span>
            </div>

            @if($materials->isEmpty())
                <div class="class-course-empty"><svg class="class-course-icon" aria-hidden="true"><use href="#ic-book"/></svg><p>Belum ada materi yang diterbitkan untuk kelas ini.</p></div>
            @else
                <div class="class-course-lesson-list">
                @foreach($materials as $material)
                    <details class="class-course-lesson" name="class-course-lessons" id="materi-{{ $material->id }}">
                        <summary class="class-course-lesson-summary" aria-controls="materi-{{ $material->id }}-konten">
                            <span class="class-course-lesson-index" aria-hidden="true">{{ sprintf('%02d', $loop->iteration) }}</span>
                            <span class="class-course-lesson-label">
                                <span class="class-course-lesson-title">{{ $material->title }}</span>
                                <span class="class-course-lesson-subline">@if($material->chapter){{ $material->chapter->title }} · @endif EDISI v{{ max(1, (int) $material->published_version_number) }}</span>
                            </span>
                            @if($material->completed_by_me_count)
                                <span class="class-course-lesson-status class-course-lesson-status--done">
                                    <svg class="class-course-icon" aria-hidden="true"><use href="#ic-check"/></svg>
                                    Selesai
                                </span>
                            @else
                                <span class="class-course-lesson-status">Belum selesai</span>
                            @endif
                            <svg class="class-course-icon class-course-chevron" aria-hidden="true"><use href="#ic-chevron-down"/></svg>
                        </summary>
                        <div class="class-course-lesson-body" id="materi-{{ $material->id }}-konten">
                            <x-learning-markdown :content="$material->content" />

                            @if($material->mediaAssets->isNotEmpty())
                                <section class="class-course-resources" aria-label="Lampiran materi">
                                    <h3>Lampiran pembelajaran</h3>
                                    <ul>
                                    @foreach($material->mediaAssets as $media)
                                        <li>
                                            @if($media->kind === 'pdf')
                                                <a href="{{ route('member.classes.material.media.pdf', [$learningClass, $material, $media]) }}">Unduh PDF privat — {{ $media->title }}</a>
                                            @elseif($media->kind === 'video_link')
                                                <a href="{{ $media->external_url }}" target="_blank" rel="noopener noreferrer nofollow">Buka video eksternal — {{ $media->title }}</a>
                                            @endif
                                        </li>
                                    @endforeach
                                    </ul>
                                    <p>Tautan video membuka penyedia di luar Siapegawai.</p>
                                </section>
                            @endif

                            <div class="class-course-lesson-action">
                                @if($material->completed_by_me_count)
                                    <span class="class-course-complete"><svg class="class-course-icon" aria-hidden="true"><use href="#ic-check"/></svg> Sudah ditandai selesai</span>
                                    @php($completedVersion = $material->completions->first()?->completed_version_number)
                                    @if($completedVersion && $completedVersion < max(1, (int) $material->published_version_number))
                                        <p class="member-version-notice">Materi diperbarui sejak Anda menyelesaikan edisi v{{ $completedVersion }}. Progres selesai Anda tetap tersimpan.</p>
                                    @endif
                                @else
                                    <form method="POST" action="{{ route('member.classes.material.complete', [$learningClass, $material]) }}">
                                        @csrf
                                        <button class="button button-primary" type="submit">Tandai materi selesai</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </details>
                @endforeach
                </div>
            @endif
            <p class="class-course-privacy"><svg class="class-course-icon" aria-hidden="true"><use href="#ic-info"/></svg> Materi privat hanya untuk peserta dengan akses aktif. Jangan membagikannya ke pihak lain.</p>
        </section>
        <aside class="class-course-sidebar" aria-label="Latihan dan silabus kelas">
            <section class="class-course-practice" aria-labelledby="class-practice-title">
                <div class="class-course-heading-inline">
                    <svg class="class-course-icon" aria-hidden="true"><use href="#ic-clipboard"/></svg>
                    <h2 id="class-practice-title">Latihan Kelas</h2>
                </div>
                <p>Uji pemahaman melalui soal kelas. Hasilnya terpisah dari progres materi dan Tryout CAT.</p>
                <a class="class-course-practice-link" href="{{ route('member.class-practices.index', $learningClass) }}">
                    Lihat latihan dan hasil
                    <svg class="class-course-icon" aria-hidden="true"><use href="#ic-arrow-right"/></svg>
                </a>
            </section>

            @if($learningClass->syllabus)
                <details class="class-course-syllabus">
                    <summary aria-controls="silabus-kelas-konten">
                        <svg class="class-course-icon" aria-hidden="true"><use href="#ic-book"/></svg>
                        Silabus kelas
                        <svg class="class-course-icon class-course-chevron" aria-hidden="true"><use href="#ic-chevron-down"/></svg>
                    </summary>
                    <div class="class-course-syllabus-body" id="silabus-kelas-konten">
                        <x-learning-markdown :content="$learningClass->syllabus" />
                    </div>
                </details>
            @endif
        </aside>
    </div>
</div>
@endsection
