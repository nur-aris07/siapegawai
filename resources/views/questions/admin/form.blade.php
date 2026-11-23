@php
  $selected = $version?->options?->firstWhere('is_correct', true)?->label;
  $existingOptions = $version?->options?->keyBy('label');
@endphp
<section class="admin-v1-panel admin-v1-form-panel fig-workbench">
  <h2>{{ !$question ? 'Buat draft baru' : ($version?->review_status === 'draft' ? 'Edit draft v'.$version->version_number : 'Buat revisi dari versi terbit') }}</h2>
  @if($question && $version?->review_status === 'published')
  <p class="admin-v1-note">Versi {{ $version->version_number }} sudah terbit. Menyimpan perubahan yang berbeda akan membuat <strong>draft revisi v{{ $version->version_number + 1 }}</strong>; menyimpan konten yang sama tidak menambah versi.</p>
  @endif
  @if($question && $version?->review_status === 'draft' && $question->current_version_id)
  <p class="admin-v1-note">Ini draft revisi v{{ $version->version_number }} yang belum diterbitkan. Versi aktif tetap v{{ $question->currentVersion?->version_number }}. Mengembalikan isi draft ke jawaban sebelumnya tidak otomatis membatalkan draft. Gunakan <strong>Batalkan draft revisi</strong> jika revisi tidak diperlukan.</p>
  @endif
  <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="admin-v1-editor" data-fig-editor-form>@csrf
    @if($method !== 'POST') @method($method) @endif
    @if($question)
      <input type="hidden" name="edit_token" value="{{ $editToken }}">
      <input type="hidden" name="figural_draft" value="1">
    @else
      <input type="hidden" name="draft_shell" value="1">
    @endif
    @if($errors->any())
      <div class="fig-editor-errors" role="alert"><strong>Draft belum tersimpan.</strong>
      <ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
    @endif
    <div class="fig-workbench-fields">
    <p class="admin-v1-note">Tulis teks, lampirkan banyak gambar, dan atur penempatan dalam satu editor. Penerbitan soal bergambar masih terkunci sampai FIG-P3/P4.</p>
    <div class="admin-v1-editor-row">
      <label class="admin-v1-field">Kode soal<input name="code" required maxlength="48" pattern="[A-Za-z0-9][A-Za-z0-9-]*" value="{{ old('code',$question?->code) }}" @if($question) readonly @endif placeholder="TWK-001"></label>
      <label class="admin-v1-field">Kategori / topik
        <select name="topic_id" required><option value="">Pilih kategori dan topik</option>
          @foreach($subjects as $subject)<optgroup label="{{ $subject->code }} — {{ $subject->name }}">@foreach($subject->topics as $topic)
            <option value="{{ $topic->id }}" @selected((string)old('topic_id',$question?->topic_id)===(string)$topic->id)>{{ $topic->name }}</option>
          @endforeach</optgroup>@endforeach
        </select>
      </label>
    </div>
    <label class="admin-v1-field">Pertanyaan <span class="fig-editor-hint">(teks opsional jika ada gambar stem)</span><textarea name="body" maxlength="5000" rows="5" placeholder="Tulis pertanyaan secara spesifik">{{ old('body',$version?->body) }}</textarea></label>
    <fieldset class="admin-v1-options"><legend>Pilihan jawaban</legend><p>Pada saat terbit, A–D wajib memiliki teks atau gambar. E opsional. Tentukan tepat satu kunci. Berkas gambar dapat dilampirkan di bagian Gambar & penempatan di bawah.</p>
      @foreach(['A','B','C','D','E'] as $letter)
        <div class="admin-v1-option-row"><span class="admin-v1-option-letter">{{ $letter }}</span>
          <label class="admin-v1-field"><span class="sr-only">Pilihan {{ $letter }}</span><input name="option_{{ strtolower($letter) }}" value="{{ old('option_'.strtolower($letter),$existingOptions?->get($letter)?->body) }}" maxlength="1200" placeholder="{{ $letter === 'E' ? 'Opsional' : 'Isi pilihan '.$letter }}"></label>
        </div>
      @endforeach
    </fieldset>
    <label class="admin-v1-field">Jawaban benar<select name="correct_option"><option value="">Pilih opsi</option>@foreach(['A','B','C','D','E'] as $letter)<option value="{{ $letter }}" @selected(old('correct_option',$selected)===$letter)>{{ $letter }}</option>@endforeach</select></label>
    <label class="admin-v1-field">Pembahasan <span class="fig-editor-hint">(boleh teks, gambar, atau gabungan keduanya saat publikasi)</span><textarea name="explanation" maxlength="5000" rows="5" placeholder="Jelaskan alasan jawaban yang benar">{{ old('explanation',$version?->explanation) }}</textarea></label>
    @include('questions.admin.media-composer')
    </div>
    @include('questions.admin.preview-workbench')
    <div class="admin-v1-editor-actions fig-workbench-actions"><button class="button button-primary" type="submit">{{ $question ? 'Simpan soal dan gambar' : 'Buat draft beserta gambar' }}</button><a href="{{ route('questions.admin.index') }}" class="button button-outline">Kembali ke daftar</a></div>
  </form>
</section>
