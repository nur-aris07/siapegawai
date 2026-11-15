@extends('layouts.admin')
@section('title', 'Pengaturan Website')
@section('content')
@php
  $dialog = $creatingCustom || $selectedKey !== null;
  $editingBuiltin = $selectedKey !== null && array_key_exists($selectedKey, $fields);
  $editingCustom = $selectedKey !== null && array_key_exists($selectedKey, $custom);
@endphp
<div class="admin-v1-heading site-settings-heading">
  <div>
    <p class="admin-v1-crumb">Operasional / Website</p>
    <h1>Pengaturan Website</h1>
    <p>Kelola nilai website dan pengaturan tambahan.</p>
  </div>
</div>
<section class="admin-v1-panel site-settings-panel" aria-labelledby="site-settings-list-title">
  <div class="site-settings-toolbar">
    <div><h2 id="site-settings-list-title">Daftar pengaturan</h2><p>Nilai dapat diubah tanpa menghapus kuncinya.</p></div>
    <div class="site-settings-toolbar-actions">
      <a class="button button-outline" href="{{ route('home') }}" target="_blank" rel="noopener noreferrer">Lihat website</a>
      <a class="button button-primary" href="{{ route('admin.site-settings.index', ['create' => 1]) }}" id="site-settings-create" data-site-settings-open-editor>Tambah pengaturan</a>
    </div>
  </div>
  <p id="site-settings-editor-message" class="site-settings-error site-settings-load-error" role="alert" hidden></p>
  <div class="site-settings-filters" data-site-settings-table data-url="{{ route('admin.site-settings.index') }}">
    <label class="site-settings-search-label" for="site-settings-search">Cari pengaturan
      <input id="site-settings-search" type="search" placeholder="Cari nama, kunci, atau nilai" autocomplete="off">
    </label>
    <label class="site-settings-length-label" for="site-settings-page-size">Tampilkan
      <select id="site-settings-page-size"><option value="10">10</option><option value="25">25</option><option value="50">50</option></select>
    </label>
    <button type="button" class="button button-outline button-compact" id="site-settings-refresh">Muat ulang</button>
  </div>
  <div class="table-responsive site-settings-scroll">
    <table class="data-table admin-v1-table site-settings-table" aria-label="Daftar pengaturan website">
      <thead><tr><th scope="col"><button type="button" data-site-settings-sort="label" aria-label="Urutkan berdasarkan pengaturan">Pengaturan <span aria-hidden="true">↕</span></button></th>
        <th scope="col"><button type="button" data-site-settings-sort="key" aria-label="Urutkan berdasarkan kunci">Kunci <span aria-hidden="true">↕</span></button></th>
        <th scope="col"><button type="button" data-site-settings-sort="value" aria-label="Urutkan berdasarkan nilai">Nilai <span aria-hidden="true">↕</span></button></th>
        <th scope="col">Tindakan</th></tr></thead>
      <tbody id="site-settings-rows">
        @foreach($rows as $row)
          <tr><th scope="row">{{ $row['label'] }}</th><td class="site-settings-key">{{ $row['key'] }}</td>
            <td class="site-settings-value">{{ $row['value'] !== '' ? $row['value'] : '—' }}</td>
            <td><div class="site-settings-row-actions"><a href="{{ route('admin.site-settings.index', ['setting' => $row['key']]) }}" data-site-settings-open-editor>Edit</a>
              <button type="button" disabled aria-label="Hapus {{ $row['label'] }} tidak tersedia" title="Penghapusan tidak tersedia">Hapus</button>
            </div></td></tr>
        @endforeach
      </tbody>
    </table>
  </div>
  <div class="admin-v1-table-foot site-settings-table-foot"><span id="site-settings-info" aria-live="polite">{{ count($rows) }} pengaturan</span>
    <div><button class="button button-outline button-compact" id="site-settings-prev" type="button" disabled>Sebelumnya</button>
      <button class="button button-outline button-compact" id="site-settings-next" type="button" disabled>Berikutnya</button></div>
  </div>
</section>
<p class="site-settings-scope-note">Pengaturan tambahan tidak otomatis tampil di website. Paket, legal, dan kredensial dikelola terpisah.</p>

@if($dialog)
<dialog id="site-settings-dialog" class="site-settings-dialog" data-open-on-load open aria-labelledby="site-settings-dialog-title">
  <div class="site-settings-dialog-head">
    <div><h2 id="site-settings-dialog-title">{{ $creatingCustom ? 'Tambah pengaturan' : 'Edit pengaturan' }}</h2>
      <p>{{ $creatingCustom ? 'Buat nama dan nilai pengaturan baru.' : 'Perbarui nilai tanpa mengganti kunci.' }}</p></div>
    <a class="site-settings-close" href="{{ route('admin.site-settings.index') }}" aria-label="Tutup dialog">Tutup</a>
  </div>
  <form method="POST" action="{{ route('admin.site-settings.preview') }}" class="site-settings-dialog-form">
    @csrf
    @if($creatingCustom || $editingCustom)
      <input type="hidden" name="mode" value="{{ $creatingCustom ? 'custom_create' : 'custom_edit' }}">
      <input type="hidden" name="version" value="{{ $customVersion }}">
      @if($creatingCustom)
        <label class="admin-v1-field" for="site-settings-custom-label">Nama pengaturan
          <input id="site-settings-custom-label" name="custom_label" value="{{ old('custom_label') }}" required maxlength="80" autocomplete="off" placeholder="Contoh: Judul informasi beranda">
        </label>
        @error('custom_label')<p class="site-settings-error" role="alert">{{ $message }}</p>@enderror
        <p class="site-settings-hint">Kunci `custom_*` dibuat otomatis dari nama, lalu tidak dapat diubah.</p>
      @else
        <input type="hidden" name="custom_key" value="{{ $selectedKey }}">
        <p class="site-settings-key-note">{{ $selectedKey }}</p>
      @endif
      <label class="admin-v1-field" for="site-settings-custom-value">Nilai
        <textarea id="site-settings-custom-value" name="custom_value" maxlength="500" rows="4" required>{{ old('custom_value', $editingCustom ? $custom[$selectedKey] : '') }}</textarea>
      </label>
      @error('custom_value')<p class="site-settings-error" role="alert">{{ $message }}</p>@enderror
      <p class="site-settings-hint">Teks biasa, bukan HTML atau tempat menyimpan token/password. Tidak otomatis tampil di website.</p>
    @elseif($editingBuiltin)
      @php($selected = $fields[$selectedKey])
      <input type="hidden" name="version" value="{{ $version }}">
      @foreach($fields as $key => $meta)
        @if($key !== $selectedKey)<input type="hidden" name="{{ $key }}" value="{{ $values[$key] }}">@endif
      @endforeach
      <p class="site-settings-key-note">{{ $selectedKey }}</p>
      <label class="admin-v1-field" for="site-settings-input-value">{{ $selected['label'] }}
        <input id="site-settings-input-value" name="{{ $selectedKey }}" type="{{ $selected['type'] === 'email' ? 'email' : ($selected['type'] === 'text' ? 'text' : 'url') }}"
          value="{{ old($selectedKey, $values[$selectedKey]) }}" maxlength="{{ $selected['max'] }}"
          @if($selected['type'] === 'text') required @endif autocomplete="off"
          placeholder="{{ $selected['type'] === 'text' ? $selected['default'] : ($selected['type'] === 'email' ? 'bantuan@domain-resmi.id' : 'https://...') }}">
      </label>
      @error($selectedKey)<p class="site-settings-error" role="alert">{{ $message }}</p>@enderror
    @endif
    @error('settings')<p class="site-settings-error" role="alert">{{ $message }}</p>@enderror
    <div class="site-settings-editor-actions">
      <button class="button button-primary" type="submit">Pratinjau perubahan</button>
      <a href="{{ route('admin.site-settings.index') }}" class="button button-outline">Batal</a>
    </div>
  </form>
</dialog>
@endif
@endsection
