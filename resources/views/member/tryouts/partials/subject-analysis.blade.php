<section class="member-history-section" aria-labelledby="{{ $headingId }}">
    <h2 id="{{ $headingId }}" class="member-history-section-heading">{{ $title }}</h2>
    @if($subjectAnalysis->isEmpty())
        <p class="member-disclaimer">Belum ada data materi untuk ditampilkan.</p>
    @else
        <div class="member-history-list">
            @foreach($subjectAnalysis as $subjectRow)
                <article class="member-history-record">
                    <div class="member-history-record-main">
                        <h3>{{ $subjectRow->code }} · {{ $subjectRow->name }}</h3>
                        <p>{{ $subjectRow->total }} soal · {{ $subjectRow->correct }} benar · {{ $subjectRow->wrong }} salah · {{ $subjectRow->unanswered }} tidak dijawab</p>
                    </div>
                    <div class="member-history-result">
                        <strong>{{ $subjectRow->accuracy === null ? '—' : number_format($subjectRow->accuracy, 1, ',', '.') . '%' }}</strong>
                        <span>Ketepatan dari {{ $subjectRow->answered }} dijawab</span>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
    <p class="member-disclaimer">Ketepatan = jawaban benar dibagi soal yang dijawab. Soal kosong dihitung terpisah. Untuk riwayat keseluruhan, soal yang dikerjakan pada beberapa percobaan dihitung setiap kali percobaan selesai. Ini bukan nilai berbobot resmi SKD/TKP.</p>
</section>
