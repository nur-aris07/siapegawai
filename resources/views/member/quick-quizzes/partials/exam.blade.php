<section class="qq-exam" id="qq-exam"
         data-phase="{{ $attempt->phase }}"
         data-expires-ms="{{ $attempt->question_expires_at->getTimestampMs() }}"
         data-feedback-until-ms="{{ $attempt->feedback_until?->getTimestampMs() ?? 0 }}"
         data-server-now-ms="{{ now('UTC')->getTimestampMs() }}"
         data-next-url="{{ route('member.quick-quizzes.next', $attempt) }}"
         data-answer-url="{{ route('member.quick-quizzes.answer', $attempt) }}">
    <div class="qq-exam-progress" aria-label="Progres Quiz Kilat">
        <span>Soal {{ $attempt->current_index + 1 }}/{{ $attempt->total_questions }}</span>
        <span>{{ $attempt->phase === 'feedback' ? 'Jawaban terkunci · lihat pembahasan' : 'Pilih satu jawaban' }}</span>
    </div>
    <div class="qq-question-label">{{ $item->subject_code_snapshot ?? 'SOAL' }} · {{ $item->topic_name_snapshot ?? 'Quiz Kilat' }}</div>
    <h2 id="qq-question-title" tabindex="-1">{{ $item->version->body }}</h2>
    @if($attempt->phase === 'question')
        <form id="qq-answer-form" action="{{ route('member.quick-quizzes.answer', $attempt) }}" method="POST">
            @csrf
            <input type="hidden" name="item_id" value="{{ $item->id }}">
            <fieldset class="qq-answers">
                <legend>Pilih jawaban untuk mengunci soal</legend>
                @foreach($item->version->options as $opt)
                    <label class="qq-answer-choice">
                        <input type="radio" name="answer" value="{{ $opt->label }}" required>
                        <span class="qq-letter">{{ $opt->label }}</span>
                        <span class="qq-option-text">{{ $opt->body }}</span>
                    </label>
                @endforeach
            </fieldset>
            <button type="submit" class="button button-primary" id="qq-answer-submit">Kunci jawaban</button>
            <p class="qq-help" id="qq-save-status" role="status" aria-live="polite">Pilih satu jawaban; pilihan langsung dikunci saat JavaScript aktif. Waktu ditentukan server.</p>
        </form>
    @else
        <div class="qq-feedback" aria-labelledby="qq-feedback-heading">
            <h3 id="qq-feedback-heading" tabindex="-1" class="qq-feedback-heading {{ $answer?->status === 'timeout' ? 'qq-feedback-heading--timeout' : ($answer?->is_correct ? 'qq-feedback-heading--correct' : 'qq-feedback-heading--incorrect') }}">
                {{ $answer?->status === 'timeout' ? 'Waktu habis' : ($answer?->is_correct ? 'Jawaban benar' : 'Jawaban belum tepat') }}
            </h3>
            <p class="qq-feedback-summary">Jawaban Anda: <strong>{{ $answer?->answer_label ?? 'Tidak dijawab' }}</strong> · Kunci benar: <strong>{{ $correct?->label }}</strong></p>
            <div class="qq-answers qq-answers--feedback" role="list" aria-label="Pilihan jawaban dan koreksi">
                @foreach($item->version->options as $opt)
                    @php
                        $isCorrect = $correct?->label === $opt->label;
                        $isIncorrectSelection = $answer?->answer_label === $opt->label && !$isCorrect;
                        $isSelectedCorrect = $answer?->answer_label === $opt->label && $isCorrect;
                    @endphp
                    <div role="listitem" class="qq-answer-choice {{ $isCorrect ? 'qq-answer-choice--correct' : ($isIncorrectSelection ? 'qq-answer-choice--incorrect' : '') }}">
                        <span class="qq-choice-marker" aria-hidden="true">{{ $isCorrect ? '✓' : ($isIncorrectSelection ? '×' : '○') }}</span>
                        <span class="qq-letter">{{ $opt->label }}</span>
                        <span class="qq-option-text">{{ $opt->body }}</span>
                        @if($isCorrect)
                            <span class="qq-choice-tag">{{ $isSelectedCorrect ? 'Jawaban Anda · benar' : 'Kunci benar' }}</span>
                        @elseif($isIncorrectSelection)
                            <span class="qq-choice-tag">Jawaban Anda · salah</span>
                        @endif
                    </div>
                @endforeach
            </div>
            <div class="qq-explanation" aria-label="Pembahasan soal">
                <h3>Pembahasan</h3>
                <p>{{ $item->version->explanation }}</p>
            </div>
        </div>
        <form method="POST" action="{{ route('member.quick-quizzes.next', $attempt) }}" id="qq-next-form">
            @csrf
            <button type="submit" class="button button-primary" id="qq-next-button">{{ $attempt->current_index + 1 === $attempt->total_questions ? 'Lihat hasil' : 'Soal berikutnya →' }}</button>
            <p class="qq-help" id="qq-feedback-status" role="status" aria-live="polite">Feedback tampil sekurangnya 5 detik sebelum berpindah. Jika koneksi terputus, sesi dapat dipulihkan.</p>
        </form>
    @endif
</section>
