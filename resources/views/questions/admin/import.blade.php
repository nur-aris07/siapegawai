@extends('layouts.admin')
@section('title', 'Content Studio · Impor Soal')
@section('content')
<div class="qi-page qi-import-page">
 <div class="admin-v1-heading qi-heading">
  <div><p class="admin-v1-crumb"><a href="{{ route('questions.admin.index') }}">Bank soal</a> / Content Studio</p><h1>Content Studio — Impor soal</h1><p>Masukkan banyak soal sekaligus melalui template. Setiap soal masuk sebagai <strong>draft</strong>, bukan langsung terbit.</p></div>
  <a class="button button-outline button-compact" href="{{ route('questions.admin.index') }}">Kembali ke Bank Soal</a>
 </div>
 <div class="qi-callout qi-callout-info" role="note"><svg class="qi-icon" aria-hidden="true"><use href="#ic-info"></use></svg><span><strong>Alur aman:</strong> unduh template → isi kolom → unggah untuk preview → periksa hasil → konfirmasi bila semua valid. Batas <strong>{{ $maxRecords }} soal</strong>; CSV hingga <strong>{{ number_format($maxBytes,0,',','.') }} byte</strong>, ZIP hingga <strong>25 MiB</strong>.</span></div>
 <section class="admin-v1-panel qi-panel" aria-labelledby="qi-help-title">
  <div class="qi-section-heading"><div><p class="qi-step">LANGKAH 01</p><h2 id="qi-help-title">Siapkan berkas impor</h2><p>Gunakan contoh ZIP untuk soal bergambar, atau template CSV 11 kolom untuk soal tanpa gambar.</p></div><svg class="qi-icon qi-icon-soft" aria-hidden="true"><use href="#ic-clipboard"></use></svg></div>
  <div class="qi-tool-actions" aria-label="Template dan bantuan impor">
   <div class="qi-tool-actions-main">
    <a href="{{ route('questions.import.zip-template') }}" class="button button-primary"><svg class="qi-icon" aria-hidden="true"><use href="#ic-package"></use></svg> Contoh ZIP+CSV</a>
    <a href="{{ route('questions.import.template') }}" class="button button-outline"><svg class="qi-icon" aria-hidden="true"><use href="#ic-clipboard"></use></svg> Unduh template CSV</a>
   </div>
   <div class="qi-tool-actions-links">
    <a href="{{ route('questions.import.references') }}" class="qi-text-action"><svg class="qi-icon" aria-hidden="true"><use href="#ic-book"></use></svg> Daftar materi &amp; topik</a>
    <button type="button" class="qi-text-action" data-import-dialog-open aria-haspopup="dialog" aria-controls="question-import-help"><svg class="qi-icon" aria-hidden="true"><use href="#ic-info"></use></svg> Panduan langkah demi langkah</button>
    <a href="{{ route('questions.qc.index') }}" class="qi-text-action"><svg class="qi-icon" aria-hidden="true"><use href="#ic-check"></use></svg> QC Workspace</a>
   </div>
  </div>
  <p class="qi-muted">Template CSV menyediakan 11 kolom dan satu baris kosong; contoh ZIP menyertakan berkas media. Gunakan pasangan <code>subject_code</code> dan <code>topic_slug</code> dari daftar referensi, bukan nama materi atau ID database.</p>
 </section>
 <section class="admin-v1-panel qi-panel" aria-labelledby="qi-upload-title">
  <div class="qi-section-heading"><div><p class="qi-step">LANGKAH 02</p><h2 id="qi-upload-title">Unggah &amp; validasi</h2><p>File diperiksa terlebih dahulu. Belum ada soal yang tersimpan ketika preview dibuat.</p></div><svg class="qi-icon qi-icon-soft" aria-hidden="true"><use href="#ic-check"></use></svg></div>
  <form method="POST" action="{{ route('questions.import.upload') }}" enctype="multipart/form-data" class="qi-upload-form">@csrf
   <fieldset class="qi-format-chooser" aria-describedby="qi-format-description">
    <legend>Format file</legend>
    <label class="qi-format-option"><input type="radio" name="mode" value="csv" checked data-qi-mode><span class="qi-format-option-copy"><strong>CSV biasa</strong><small>11 kolom</small></span></label>
    <label class="qi-format-option"><input type="radio" name="mode" value="zip" data-qi-mode><span class="qi-format-option-copy"><strong>ZIP+CSV</strong><small>Dengan media privat</small></span></label>
   </fieldset>
   <p class="qi-muted qi-mode-description" id="qi-format-description">CSV menggunakan template 11 kolom. Mode ZIP berisi questions.csv, media.csv, dan gambar privat. Semua soal masuk sebagai draft.</p>
   <div class="qi-dropzone" data-qi-dropzone aria-label="Area unggah file CSV">
    <svg class="qi-icon qi-upload-icon" aria-hidden="true"><use href="#ic-clipboard"></use></svg>
    <strong data-qi-drop-title>Tarik dan lepas file CSV di sini</strong>
    <span data-qi-drop-description>CSV UTF-8; BOM didukung.</span>
    <label for="import-file" class="button button-outline qi-choose-label"><span data-qi-choose-text>Pilih file CSV</span>
     <input id="import-file" name="file" class="qi-file-input" type="file" accept=".csv,text/csv" required aria-describedby="qi-upload-hint qi-file-status" />
    </label>
    <span class="qi-selected" id="qi-file-status" data-qi-selected role="status" aria-live="polite">Belum ada file dipilih</span>
   </div>
   <p class="qi-muted qi-upload-hint" id="qi-upload-hint" data-qi-upload-hint data-qi-hint-csv="CSV: maksimum {{ $maxRecords }} soal dan {{ number_format($maxBytes,0,',','.') }} byte. Jika server menolak unggahan, periksa batas PHP/web server." data-qi-hint-zip="ZIP + CSV bergambar: maksimum {{ $maxRecords }} soal dan 25 MiB. Jika server menolak unggahan, periksa batas PHP/web server.">CSV: maksimum {{ $maxRecords }} soal dan {{ number_format($maxBytes,0,',','.') }} byte. Jika server menolak unggahan, periksa batas PHP/web server.</p>
   @error('file')<p class="qi-callout qi-callout-danger" role="alert"><svg class="qi-icon" aria-hidden="true"><use href="#ic-alert-circle"></use></svg>{{ $message }}</p>@enderror
   <button type="submit" class="button button-primary qi-submit"><svg class="qi-icon" aria-hidden="true"><use href="#ic-check"></use></svg> Validasi &amp; lihat preview</button>
  </form>
 </section>
 <section class="admin-v1-panel qi-panel" aria-labelledby="qi-batches-title">
  <div class="qi-section-heading"><div><p class="qi-step">RIWAYAT</p><h2 id="qi-batches-title">Batch impor milik Anda</h2><p>Batch gagal atau belum dikonfirmasi dapat dibuang tanpa menghapus soal maupun riwayat yang sudah di-commit.</p></div></div>
  <div class="table-scroll" tabindex="0" aria-label="Daftar batch impor"><table class="data-table admin-v1-table qi-table"><thead><tr><th>Waktu</th><th>Record</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
  @forelse($batches as $batch)
  <tr><td>{{ $batch->created_at->format('d M Y H:i') }}</td><td>{{ $batch->row_count }}</td><td><span class="qi-status qi-status-{{ $batch->status }}">{{ ['ready'=>'Siap','invalid'=>'Invalid','failed'=>'Gagal','expired'=>'Kedaluwarsa','committed'=>'Tersimpan'][$batch->status] ?? $batch->status }}</span></td><td><div class="qi-row-actions"><a href="{{ route('questions.import.show', $batch->id) }}">Lihat</a>
  @if(in_array($batch->status, ['ready','invalid','failed','expired'], true))<details class="qi-discard"><summary><svg class="qi-icon" aria-hidden="true"><use href="#ic-trash"></use></svg> Buang</summary><form method="POST" action="{{ route('questions.import.discard', $batch->id) }}">@csrf @method('DELETE')<p>Buang batch ini? Berkas privat akan dihapus. Tidak ada soal yang terhapus.</p><label><input type="checkbox" name="confirmed_discard" value="1" required> Saya mengerti</label><button type="submit" class="button qi-danger-button">Buang batch</button></form></details>@endif</div></td></tr>
  @empty<tr><td colspan="4">Belum ada batch impor. Unggah CSV atau ZIP untuk memulai.</td></tr>@endforelse
  </tbody></table></div>
 </section>
 <dialog id="question-import-help" class="qi-dialog" aria-labelledby="qi-dialog-title" aria-describedby="qi-dialog-desc">
  <div class="qi-dialog-head"><div><p class="qi-step">PANDUAN</p><h2 id="qi-dialog-title">Cara mengisi template impor</h2></div><button type="button" class="qi-icon-button" data-import-dialog-close aria-label="Tutup panduan"><svg class="qi-icon" aria-hidden="true"><use href="#ic-x"></use></svg></button></div>
  <div class="qi-dialog-body" id="qi-dialog-desc">
   <ol class="qi-guide-list">
    <li><strong>Unduh template CSV.</strong> Format telah memiliki 11 header dalam urutan yang benar; isi baris kosong, dan tambahkan baris bila diperlukan.</li>
    <li><strong>Unduh referensi aktif.</strong> Isi <code>subject_code</code> dan <code>topic_slug</code> dari pasangan pada file referensi. Nama tampilan tidak dipakai untuk identifikasi.</li>
    <li><strong>Isi pertanyaan dan jawaban.</strong> Gunakan kode unik A–Z, 0–9, minus (maks. 48 karakter); <code>body</code> dan <code>explanation</code> masing-masing 12–5.000 karakter. Opsi A–D wajib; E opsional. Kunci E hanya bila opsi E diisi.</li>
    <li><strong>Simpan sebagai CSV UTF-8.</strong> Pakai pemisah koma. Teks berisi koma/baris baru dibungkus tanda kutip ganda; tanda kutip di dalam teks ditulis dua kali. Pertahankan header aslinya.</li>
    <li><strong>Unggah, periksa hasil, lalu konfirmasi.</strong> File dengan satu kesalahan pun tidak bisa disimpan. Koreksi file dan unggah ulang.</li>
   </ol>
   <div class="qi-callout qi-callout-info"><svg class="qi-icon" aria-hidden="true"><use href="#ic-info"></use></svg><span>Contoh teks CSV: <code>"Apa arti ""integritas"", dalam pelayanan?"</code>. Setiap opsi maksimal 1.200 karakter.</span></div>
  </div>
  <div class="qi-dialog-actions"><a href="{{ route('questions.import.template') }}" class="button button-outline">Unduh template</a><button type="button" class="button button-primary" data-import-dialog-close>Tutup panduan</button></div>
 </dialog>
</div>
@endsection
