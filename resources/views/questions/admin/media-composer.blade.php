@php
  $composerSlots = ['stem'=>'Pertanyaan','option_A'=>'Pilihan A','option_B'=>'Pilihan B','option_C'=>'Pilihan C','option_D'=>'Pilihan D','option_E'=>'Pilihan E','explanation'=>'Pembahasan'];
  $mediaIsDraft = $question && $version?->review_status === 'draft';
  $persisted = $mediaIsDraft ? $version->media->sortBy([['slot','asc'],['position','asc']]) : collect();
  $mediaGroups = $persisted->groupBy('slot');
@endphp
<section class="fig-composer" data-fig-composer aria-labelledby="fig-composer-heading">
  <div class="fig-composer-head">
    <div><h3 id="fig-composer-heading">Gambar & susunan</h3><p>Satu editor untuk teks dan gambar. Letakkan beberapa gambar, lalu geser kartu langsung ke bagian soal yang dituju.</p></div>
    <span class="fig-composer-limit">JPEG / PNG / WebP · 5 MB/berkas · 12 baru/simpan</span>
  </div>
  <div class="fig-dropzone" data-fig-dropzone tabindex="0" role="group" aria-label="Letakkan gambar atau pilih berkas">
    <label class="fig-dropzone-label" for="fig-batch-files">
      <svg class="fig-dropzone-icon" viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V3m0 0-4 4m4-4 4 4"/><path d="M20 16.5v2.7A1.8 1.8 0 0 1 18.2 21H5.8A1.8 1.8 0 0 1 4 19.2v-2.7"/><path d="M4 13.5a4.2 4.2 0 0 1 3.5-4.1"/></svg>
      <strong data-fig-drop-hint>Seret gambar ke sini</strong>
      <span>atau <span class="fig-dropzone-cta">Pilih gambar</span> · bisa beberapa sekaligus</span>
      <small>File selain gambar ditolak sebelum disimpan</small>
    </label>
    <input id="fig-batch-files" class="fig-dropzone-input" type="file" name="media_files[]" accept="image/jpeg,image/png,image/webp" multiple data-fig-files aria-describedby="fig-composer-status">
  </div>
  <p id="fig-composer-status" class="fig-composer-status" data-fig-status role="status" aria-live="polite">Berkas baru akan masuk ke Pertanyaan. Pindahkan lewat drag atau menu tindakan pada kartu.</p>
  <div class="fig-composer-queue-header"><h4>Hierarki gambar <span data-fig-count>{{ $persisted->count() }}</span></h4><small>Desktop: geser dari ikon pegangan. Mobile/keyboard: gunakan Pindahkan serta Naik/Turun bila ada lebih dari satu gambar dalam bagian yang sama.</small></div>
  <div class="fig-composer-hierarchy" data-fig-hierarchy>
    @foreach($composerSlots as $slot => $label)
      <section class="fig-composer-group{{ ($mediaGroups->get($slot) ?? collect())->isEmpty() ? ' is-empty' : '' }}" data-fig-group="{{ $slot }}" aria-label="Gambar untuk {{ $label }}">
        <div class="fig-composer-group-head"><strong>{{ $label }}</strong><span class="fig-composer-group-count" data-fig-group-count>{{ ($mediaGroups->get($slot) ?? collect())->count() }} gambar</span></div>
        <ol class="fig-composer-group-list" data-fig-group-list aria-label="Susunan gambar {{ $label }}">
          @foreach($mediaGroups->get($slot) ?? collect() as $media)
          <li class="fig-composer-card" data-fig-persisted data-fig-media-id="{{ $media->id }}">
            <div class="fig-composer-thumb"><img src="{{ route('questions.admin.media.preview',[$question,$media]) }}" alt="{{ $media->alt_text ?: 'Gambar soal terlampir' }}" loading="lazy"></div>
            <div class="fig-composer-card-detail">
              <div class="fig-composer-card-top"><span class="fig-composer-grip" aria-hidden="true" title="Geser gambar ke bagian atau urutan lain">⠿</span><strong>Gambar tersimpan #{{ $loop->iteration }}</strong><span class="fig-composer-position" data-fig-rank></span></div>
              <input type="hidden" name="media_existing_ids[]" value="{{ $media->id }}">
              <input type="hidden" name="media_existing_slots[]" data-fig-slot value="{{ $media->slot }}">
              <input type="hidden" name="media_existing_positions[]" value="{{ $media->position }}" data-fig-position>
              <div class="fig-composer-fields">
                <label>Teks alternatif<input name="media_existing_alts[]" data-fig-alt maxlength="300" value="{{ $media->alt_text }}" placeholder="Deskripsikan unsur penting"></label>
                <label>Keterangan<input name="media_existing_captions[]" data-fig-caption maxlength="300" value="{{ $media->caption }}" placeholder="Opsional"></label>
              </div>
              <input type="hidden" name="media_existing_remove[]" value="0" data-fig-remove-value>
              <div class="fig-composer-card-actions">
                <button type="button" data-fig-move="up">Naik</button><button type="button" data-fig-move="down">Turun</button>
                <details class="fig-composer-move-menu"><summary>Pindahkan ke…</summary><div class="fig-composer-move-options">
                  @foreach($composerSlots as $target => $targetName)<button type="button" data-fig-destination="{{ $target }}">{{ $targetName }}</button>@endforeach
                </div></details>
                <button type="button" data-fig-remove>Jadwalkan lepas</button>
              </div>
            </div>
          </li>
          @endforeach
        </ol>
        <p class="fig-composer-group-empty" data-fig-group-empty>Tarik gambar ke bagian ini</p>
      </section>
    @endforeach
  </div>
  <div data-fig-hidden-fields></div>
  @if($question && $version?->review_status === 'published')
    <p class="fig-composer-muted">Versi terbit immutable. Perubahan yang disimpan membuat draft revisi; media versi terbit tetap dipertahankan.</p>
  @endif
  <p class="fig-composer-note">Berkas privat melekat pada versi soal. Publikasi figural untuk peserta tetap terkunci sampai FIG-P3/P4.</p>
</section>
