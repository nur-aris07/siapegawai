<aside class="fig-preview" data-fig-preview aria-labelledby="fig-preview-title">
  <details class="fig-preview-disclosure" data-fig-preview-disclosure open>
    <summary class="fig-preview-mobile-summary"><span>Pratinjau soal QQ / CAT / Kelas</span><span class="fig-preview-summary-action">Buka / tutup</span></summary>
    <div class="fig-preview-content">
  <header class="fig-preview-header">
    <h3 id="fig-preview-title">Pratinjau tampilan soal</h3>
    <p>Simulasi admin, tanpa attempt, timer, kuota, atau skor.</p>
  </header>
  <div class="fig-preview-modes" role="group" aria-label="Konteks pratinjau">
    <button type="button" class="is-active" data-fig-context="qq" aria-pressed="true">Quick Quiz</button>
    <button type="button" data-fig-context="cat" aria-pressed="false">CAT</button>
    <button type="button" data-fig-context="class" aria-pressed="false">Latihan Kelas</button>
  </div>
  @if($question && $question->currentVersion)
    <div class="fig-preview-versions" role="group" aria-label="Sumber pratinjau">
      <button type="button" data-fig-version="draft" aria-pressed="true">Draft di editor</button>
      <button type="button" data-fig-version="published" aria-pressed="false">Terbit v{{ $question->currentVersion->version_number }}</button>
    </div>
    <template data-fig-published-source>
      <span data-fig-published-body>{{ $question->currentVersion->body }}</span>
      <span data-fig-published-explanation>{{ $question->currentVersion->explanation }}</span>
      <span data-fig-published-correct>{{ $question->currentVersion->options->firstWhere('is_correct',true)?->label }}</span>
      @foreach($question->currentVersion->options as $option)
        <span data-fig-published-option="{{ $option->label }}">{{ $option->body }}</span>
      @endforeach
      @foreach($question->currentVersion->media as $media)
        <img data-fig-published-media="{{ $media->slot }}" data-fig-order="{{ $media->position }}" src="{{ route('questions.admin.media.preview',[$question,$media]) }}" alt="{{ $media->alt_text ?: 'Gambar ilustrasi soal' }}">
      @endforeach
    </template>
  @endif
  <div class="fig-preview-paper">
    <div class="fig-preview-meta"><strong data-fig-context-title>Quick Quiz</strong><span>Soal contoh · hanya visual</span></div>
    <div data-fig-preview-question class="fig-preview-question"></div>
    <div data-fig-preview-options class="fig-preview-options"></div>
    <details class="fig-preview-review"><summary>Lihat kunci dan pembahasan (hanya admin)</summary><div data-fig-preview-explanation></div></details>
  </div>
  <p class="fig-preview-note">Simulasi admin saja; rendering dan akses gambar peserta tetap scope FIG-P3.</p>
    </div>
  </details>
</aside>
