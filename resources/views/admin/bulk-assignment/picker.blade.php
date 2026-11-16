<section class="admin-v1-panel sg-bulk-assign" data-bulk-assignment
  data-mode="{{ !empty($bulkCreateSearchUrl) ? 'create' : 'append' }}" data-max="{{ $bulkMax ?? ($bulkDomain==='qq' ? 40 : 100) }}"
  data-search-url="{{ !empty($bulkCreateSearchUrl) ? $bulkCreateSearchUrl : route('admin.bulk-assignment.search', ['domain'=>$bulkDomain, 'target'=>$bulkTarget]) }}"
  @if(empty($bulkCreateSearchUrl))data-preview-url="{{ route('admin.bulk-assignment.preview', ['domain'=>$bulkDomain, 'target'=>$bulkTarget]) }}"@endif
  @if(empty($bulkCreateSearchUrl))data-confirm-url="{{ route('admin.bulk-assignment.confirm', ['domain'=>$bulkDomain, 'target'=>$bulkTarget]) }}"@endif>
  @if(empty($bulkCreateSearchUrl))
    <header class="sg-bulk-assign__head">
      <div><h2>Tambahkan soal ke draft</h2><p class="admin-v1-muted">Cari dan pilih soal terbit untuk ditambahkan setelah pratinjau dan konfirmasi.</p></div>
      <span class="sg-bulk-assign__pill">Hanya menambah</span>
    </header>
  @endif
  @if(empty($bulkCreateSearchUrl))<p class="sg-bulk-assign__hint">Simpan perubahan pada form editor di atas terlebih dahulu. Penugasan batch tidak mengubah judul, timer, susunan lama, maupun versi terbit.</p>@endif
  <div class="sg-bulk-assign__filters">
    <label>Cari kode atau pertanyaan <input type="search" data-bulk-search maxlength="100" placeholder="Cari soal terbit" autocomplete="off"></label>
    <label>Kode materi (opsional) <input type="text" data-bulk-subject maxlength="12" placeholder="Contoh: TWK"></label>
    <button type="button" class="button button-outline button-compact" data-bulk-find>Cari</button>
  </div>
  <div class="sg-bulk-assign__listing" tabindex="0" role="region" aria-label="Tabel pencarian soal terbit"><table class="sg-bulk-table"><thead><tr><th scope="col">Pilih</th><th scope="col">Kode · materi · versi</th><th scope="col">Pertanyaan</th></tr></thead><tbody data-bulk-results></tbody></table></div>
  <div class="sg-bulk-assign__pagination" role="group" aria-label="Navigasi hasil dan pilihan soal">
    <div class="sg-bulk-assign__pager">
      <button type="button" class="button button-outline button-compact sg-bulk-page-arrow" data-bulk-prev title="Halaman sebelumnya" aria-label="Halaman sebelumnya">&lsaquo;</button>
      <span data-bulk-page aria-live="polite">Halaman 1 dari 1</span>
      <button type="button" class="button button-outline button-compact sg-bulk-page-arrow" data-bulk-next title="Halaman berikutnya" aria-label="Halaman berikutnya">&rsaquo;</button>
    </div>
    <div class="sg-bulk-assign__page-actions">
      <button type="button" class="button button-outline button-compact" data-bulk-select-page title="Pilih seluruh hasil pada halaman saat ini">Pilih halaman ini</button>
      <button type="button" class="button button-outline button-compact" data-bulk-unselect-page title="Batalkan pilihan pada halaman saat ini">Batalkan pilihan halaman</button>
    </div>
  </div>
  <div class="sg-bulk-assign__selection">
    <div class="sg-bulk-assign__selection-head"><h3>Pilihan untuk ditambahkan (<span data-bulk-count>0</span>)</h3><button type="button" class="button button-outline button-compact" data-bulk-clear>Kosongkan pilihan</button></div>
    <p class="admin-v1-muted">Urutan di bawah adalah urutan final pilihan. Geser dari pegangan pada desktop atau gunakan nomor tujuan untuk lompatan jauh; pilihan tetap ada ketika berpindah halaman.</p>
    <ol data-bulk-selected class="sg-bulk-assign__chosen"></ol>
  </div>
  @if(empty($bulkCreateSearchUrl))<div class="sg-bulk-assign__actions"><button type="button" class="button button-outline" data-bulk-preview>Periksa & pratinjau</button>
    <button type="button" class="button button-primary" data-bulk-confirm disabled>Konfirmasi penambahan</button></div>@endif
  <p class="sg-bulk-assign__status" data-bulk-status role="status" aria-live="polite">Pilih soal untuk memulai. Belum ada perubahan disimpan.</p>
  <section class="sg-bulk-assign__review" data-bulk-review hidden aria-label="Hasil pratinjau">
    <h3>Hasil pemeriksaan</h3><p data-bulk-summary></p><ul data-bulk-errors></ul>
    <p class="admin-v1-muted">Jika ada kesalahan, perbaiki pilihan tanpa mengulang pencarian. Konfirmasi hanya aktif saat seluruh pilihan valid.</p>
  </section>
</section>
