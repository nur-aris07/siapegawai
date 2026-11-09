<form method="POST" action="{{ $action }}" class="admin-v1-tryout-editor" data-tryout-editor data-search-url="{{ route('tryouts.admin.search') }}">
  @csrf @if($method==='PUT') @method('PUT') @endif
  <section class="admin-v1-panel admin-v1-form-panel"><h2>Identitas dan aturan</h2>
    <div class="admin-v1-editor-row">
      <label class="admin-v1-field">Kode tryout<input name="code" maxlength="48" pattern="[A-Z][A-Z0-9-]*" value="{{ old('code',$package->code??'') }}" required @if(isset($package)) readonly @endif></label>
      <label class="admin-v1-field">Nama tryout<input name="name" maxlength="180" value="{{ old('name',$package->name??'') }}" required></label>
    </div>
    <label class="admin-v1-field">Deskripsi<textarea name="description" maxlength="2500" rows="3">{{ old('description',$package->description??'') }}</textarea></label>
    <div class="admin-v1-editor-row">
      <label class="admin-v1-field">Durasi (menit, 5–360)<input type="number" name="duration_minutes" min="5" max="360" value="{{ old('duration_minutes',$package->duration_minutes??'90') }}" required></label>
      <label class="admin-v1-field">Maksimum percobaan (1–20)<input type="number" name="max_attempts" min="1" max="20" value="{{ old('max_attempts',$package->max_attempts??'1') }}" required></label>
    </div>
  </section>
  <section class="admin-v1-panel admin-v1-form-panel"><h2>Pilih versi soal</h2><p>Daftar hasil pencarian hanya memuat soal terbit. Paket mengingat versi yang dipilih, meskipun master soal kelak diperbarui.</p>
    <div class="admin-v1-tryout-search">
      <label class="admin-v1-field">Cari berdasarkan kode atau pertanyaan<input type="search" id="tryout-question-search" placeholder="Contoh: TWK-001" autocomplete="off"></label>
      <label class="admin-v1-field">Kategori<select id="tryout-question-subject"><option value="">Semua kategori</option>@foreach($subjects as $subject)<option value="{{ $subject->code }}">{{ $subject->code }}</option>@endforeach</select></label>
      <button type="button" class="button button-outline button-compact" id="tryout-search-button">Cari soal</button>
    </div>
    <p id="tryout-search-message" class="admin-v1-muted" role="status" aria-live="polite">Cari soal terbit yang akan ditambahkan.</p>
    <div id="tryout-search-results" class="admin-v1-tryout-results"></div>
    <h3 class="admin-v1-tryout-subheading">Urutan soal terpilih (<span id="tryout-selected-count">{{ $selected->count() }}</span>/100)</h3>
    <p class="admin-v1-muted">Urutkan dengan tombol Naik / Turun. Setiap soal hanya boleh dipilih satu kali.</p>
    <ol id="tryout-selected-list" class="admin-v1-selected-list">
      @foreach($selected as $row)
        <li data-version-id="{{ $row['version_id'] }}" data-label="{{ $row['code'] }} · {{ $row['subject'] }} · v{{ $row['version'] }}">
          <span>{{ $row['code'] }} · {{ $row['subject'] }} · v{{ $row['version'] }} — {{ $row['summary'] }}</span>
          <span class="admin-v1-selected-actions"><button type="button" data-move="up" class="button button-outline button-compact">Naik</button><button type="button" data-move="down" class="button button-outline button-compact">Turun</button><button type="button" data-remove class="button button-outline button-compact">Hapus</button></span>
          <input type="hidden" name="version_ids[]" value="{{ $row['version_id'] }}">
        </li>
      @endforeach
    </ol>
  </section>
  <div class="admin-v1-editor-actions"><button type="submit" class="button button-primary">Simpan draft tryout</button><a href="{{ route('tryouts.admin.index') }}" class="button button-outline">Kembali ke daftar</a></div>
</form>
