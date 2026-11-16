{{-- Shared assignment fieldset: create selects before initial save; edit preserves pinned versions. --}}
<section class="admin-v1-panel admin-v1-form-panel sg-composer-panel">
  <div class="sg-composer-heading"><div><h2>Pilih dan susun soal</h2><p class="admin-v1-muted">Cari soal terbit, tandai pilihan, lalu periksa urutannya. Soal belum diterbitkan ketika draft disimpan.</p></div><span class="sg-composer-limit">Maksimal {{ $composerMax }} soal</span></div>
  @if($composerCreate)
    <div hidden data-sg-restore-choices>@foreach((array)old('version_ids',[]) as $oldId)@if(is_scalar($oldId) && ctype_digit((string)$oldId) && (int)$oldId > 0)<span data-sg-restore-id="{{ $oldId }}"></span>@endif @endforeach</div>
    @include('admin.bulk-assignment.picker',[ 'bulkDomain'=>$composerDomain, 'bulkTarget'=>null, 'bulkCreateSearchUrl'=>$composerSearchUrl, 'bulkMax'=>$composerMax ])
    <p class="sg-composer-note">Pilihan dan pengaturan disimpan bersama ketika tombol <strong>Simpan draft</strong> ditekan.</p>
  @else
    <div class="sg-editor-pins" data-sg-editor-pins data-max="{{ $composerMax }}">
      <div class="sg-composer-heading"><h3>Soal dalam draft (<span data-sg-pin-count>{{ $selected->count() }}</span>)</h3><span class="admin-v1-muted">Geser lewat pegangan pada desktop, atau pindah langsung ke nomor tertentu.</span></div>
      <ol class="sg-editor-pin-list" data-sg-pins>
        @foreach($selected as $row)
          <li data-version-id="{{ $row['version_id'] }}" class="sg-editor-pin">
            <span class="sg-editor-grip" aria-hidden="true">⠿</span><strong data-sg-rank>{{ $loop->iteration }}</strong>
            <span class="sg-editor-pin-label">{{ $row['code'] }} · {{ $row['subject'] }} · v{{ $row['version'] }} — {{ $row['summary'] }}</span>
            <div class="sg-editor-pin-actions"><label>Ke nomor <input type="number" data-sg-jump min="1" max="{{ $composerMax }}" value="{{ $loop->iteration }}" aria-label="Pindahkan {{ $row['code'] }} ke nomor"></label>
            <button type="button" class="button button-outline button-compact" data-sg-jump-go>Pindahkan</button><button type="button" class="button button-outline button-compact" data-sg-remove>Hapus</button></div>
            <input type="hidden" name="version_ids[]" value="{{ $row['version_id'] }}">
          </li>
        @endforeach
      </ol>
      @if($selected->isEmpty())<p class="admin-v1-muted" data-sg-pin-empty>Belum ada soal pada draft ini.</p>@endif
      <p class="admin-v1-muted">Simpan draft setelah mengubah susunan. Untuk menambah soal baru, gunakan panel Tambahkan banyak soal di bawah form.</p>
    </div>
  @endif
</section>
