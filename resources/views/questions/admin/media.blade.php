@php
  $groups = $mediaVersion?->media?->groupBy('slot') ?? collect();
  $slotLabels = ['stem'=>'Pertanyaan','option_A'=>'Pilihan A','option_B'=>'Pilihan B','option_C'=>'Pilihan C','option_D'=>'Pilihan D','option_E'=>'Pilihan E','explanation'=>'Pembahasan'];
@endphp
<section class="admin-v1-panel fig-editor-media" aria-labelledby="fig-editor-media-heading">
  <h2 id="fig-editor-media-heading">Gambar soal · versi {{ $mediaVersion->version_number }}</h2>
  <p class="admin-v1-note">Semua gambar disimpan privat dan melekat ke versi soal. Preview ini hanya tersedia bagi admin yang berizin; peserta belum dapat mengakses gambar sebelum FIG-P3/P4.</p>
  @foreach($slotLabels as $slot => $slotLabel)
    @if($groups->has($slot))
    <section class="fig-editor-slot" aria-label="Gambar {{ $slotLabel }}">
      <h3>{{ $slotLabel }}</h3>
      <div class="fig-editor-gallery">
        @foreach($groups->get($slot) as $media)
          <article class="fig-editor-item">
            <div class="fig-editor-image-wrap">
              <img src="{{ route('questions.admin.media.preview', [$question, $media]) }}" alt="{{ $media->alt_text ?: 'Preview gambar '.$slotLabel.' urutan '.$media->position }}" loading="lazy">
            </div>
            <p class="fig-editor-caption">Urutan {{ $media->position }} · {{ $media->mime_type }} · {{ number_format($media->byte_size / 1024, 1) }} KB</p>
            @if($readOnly)
              <p>{{ $media->caption }}</p>
            @else
              <form method="POST" action="{{ route('questions.admin.media.metadata', [$question,$media]) }}" class="fig-editor-item-form">@csrf
                <input type="hidden" name="edit_token" value="{{ $editToken }}">
                <label class="admin-v1-field">Teks alternatif<input name="alt_text" maxlength="300" value="{{ $media->alt_text }}" placeholder="Deskripsi gambar untuk aksesibilitas"></label>
                <label class="admin-v1-field">Keterangan gambar<input name="caption" maxlength="300" value="{{ $media->caption }}" placeholder="Keterangan opsional"></label>
                <button type="submit" class="button button-outline button-compact">Simpan keterangan</button>
              </form>
              <form method="POST" action="{{ route('questions.admin.media.detach', [$question,$media]) }}" class="fig-editor-detach">@csrf
                <input type="hidden" name="edit_token" value="{{ $editToken }}">
                <label class="check-line"><input type="checkbox" name="confirmed_detach" value="1" required> Lepas referensi gambar ini</label>
                <button type="submit" class="button button-outline button-compact">Lepas dari draft</button>
              </form>
            @endif
          </article>
        @endforeach
      </div>
      @if(!$readOnly && $groups->get($slot)->count() > 1)
        <form method="POST" action="{{ route('questions.admin.media.reorder',$question) }}" class="fig-editor-order">@csrf
          <input type="hidden" name="edit_token" value="{{ $editToken }}">
          <input type="hidden" name="slot" value="{{ $slot }}">
          <p>Ubah urutan dengan memilih gambar untuk setiap posisi (tanpa duplikasi):</p>
          <div class="fig-editor-order-fields">
          @foreach($groups->get($slot) as $i => $media)
            <label>Posisi {{ $i + 1 }}
              <select name="order[]" required>
                @foreach($groups->get($slot) as $candidate)
                  <option value="{{ $candidate->id }}" @selected($candidate->id === $media->id)>Gambar #{{ $candidate->position }}</option>
                @endforeach
              </select>
            </label>
          @endforeach
          </div>
          <button type="submit" class="button button-outline button-compact">Simpan urutan</button>
        </form>
      @endif
    </section>
    @endif
  @endforeach
  @if($groups->isEmpty()) <p class="admin-v1-muted">Belum ada gambar pada versi ini.</p> @endif
  @if(!$readOnly)
    <form method="POST" action="{{ route('questions.admin.media.upload',$question) }}" enctype="multipart/form-data" class="fig-editor-upload" data-fig-editor-upload>@csrf
      <input type="hidden" name="edit_token" value="{{ $editToken }}">
      <h3>Tambahkan gambar pada draft</h3>
      <p>JPEG/PNG/WebP · maksimum 5 MB · sisi maksimum 4096 px · maksimum 8 gambar per slot. Ganti gambar dengan melepas referensi lama dahulu.</p>
      <div class="fig-editor-upload-grid">
        <label class="admin-v1-field">Bagian soal
          <select name="slot" required>@foreach($slotLabels as $key => $title)<option value="{{ $key }}">{{ $title }}</option>@endforeach</select>
        </label>
        <label class="admin-v1-field">Posisi<input type="number" name="position" value="1" min="1" max="8" required></label>
        <label class="admin-v1-field">Gambar privat<input type="file" name="media" accept="image/jpeg,image/png,image/webp" required data-fig-editor-file></label>
        <label class="admin-v1-field">Teks alternatif<input name="alt_text" maxlength="300" placeholder="Jelaskan elemen visual penting"></label>
        <label class="admin-v1-field">Keterangan (opsional)<input name="caption" maxlength="300" placeholder="Keterangan untuk editor"></label>
      </div>
      <div data-fig-editor-local-preview class="fig-editor-local-preview" hidden aria-live="polite"></div>
      <button type="submit" class="button button-primary">Unggah gambar ke draft</button>
    </form>
  @endif
</section>
