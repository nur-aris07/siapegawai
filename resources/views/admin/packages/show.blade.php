@extends('layouts.admin')
@section('title', 'Kelola paket '.$plan->code)
@section('content')
<div class="admin-v1-heading">
  <div>
    <p class="admin-v1-crumb">Operasional / <a href="{{ route('admin.packages.index') }}">Katalog paket</a> / {{ $plan->code }}</p>
    <h1>Kelola paket: {{ $plan->name }}</h1>
    <p>Lihat versi yang terbit, periksa perubahan draft, lalu kelola publikasi dan ketersediaan secara terpisah.</p>
  </div>
</div>
<nav class="pkg-detail-nav" aria-label="Navigasi pengelolaan paket">
  <a href="{{ route('admin.packages.index') }}" class="button button-outline">Kembali ke katalog</a>
  <a href="{{ route('admin.packages.ordering') }}" class="button button-outline">Atur urutan</a>
  <a href="{{ route('admin.qq-quota.index') }}" class="button button-outline">Kebijakan kuota QQ</a>
</nav>
@if ($errors->any())
  <div class="pkg-detail-errors" role="alert"><strong>Perubahan belum disimpan.</strong>
    <ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
  </div>
@endif
<div class="pkg-detail-meta" aria-label="Identitas paket">
  <div><span>Kode paket</span><strong>{{ $plan->code }}</strong></div>
  <div><span>Jenis</span><strong>{{ $plan->is_paid ? 'Berbayar / manual' : 'Gratis' }}</strong></div>
  <div><span>Urutan</span><strong>{{ $plan->tier }}</strong></div>
  <div><span>Ketersediaan</span><strong>{{ $plan->is_archived ? 'Diarsipkan' : match($plan->availability) { 'requestable' => 'Menerima pengajuan', 'closed' => 'Pengajuan ditutup', default => 'Dalam perencanaan' } }}</strong></div>
</div>
<div class="pkg-detail-columns">
  <section class="admin-v1-panel pkg-detail-panel" aria-labelledby="pkg-published-heading">
    <div class="pkg-detail-section-head"><div><p class="pkg-detail-eyebrow">Informasi yang dilihat publik</p><h2 id="pkg-published-heading">Versi terbit</h2></div>
      <span class="pkg-detail-tag">{{ $published ? 'v'.$published->version_no.' · Terbit' : 'Belum terbit' }}</span></div>
    @if ($published)
      <div class="pkg-detail-copy"><h3>{{ $published->title }}</h3><p>{{ $published->summary }}</p>
        <h4>Manfaat pada versi terbit</h4>
        @if (count($published->benefits ?? []))<ul>@foreach($published->benefits as $benefit)<li>{{ $benefit }}</li>@endforeach</ul>
        @else<p class="pkg-hint">Belum ada manfaat deskriptif.</p>@endif
      </div>
      <p class="pkg-detail-note">Versi ini tetap dilihat peserta hingga draft berikutnya diterbitkan. Mengubah draft tidak langsung mengubah tampilan publik.</p>
    @else
      <p class="pkg-hint">Paket ini belum memiliki versi terbit. Draft pertama harus ditinjau dan diterbitkan sebelum tampil di katalog publik.</p>
    @endif
  </section>

  <section class="admin-v1-panel pkg-detail-panel" aria-labelledby="pkg-draft-heading">
    <div class="pkg-detail-section-head"><div><p class="pkg-detail-eyebrow">Perubahan yang sedang disiapkan</p><h2 id="pkg-draft-heading">Draft {{ $draft ? 'v'.$draft->version_no : 'belum ada' }}</h2></div>
      <span class="pkg-detail-tag">{{ $draft ? 'Belum terbit' : 'Tidak ada perubahan' }}</span></div>
    @if ($draft)
      <p class="pkg-hint">Edit dan simpan draft, lalu periksa kembali sebelum menerbitkannya. Versi publik tidak berubah sampai publish.</p>
      <form method="POST" action="{{ route('admin.packages.draft.update', $plan) }}" class="pkg-detail-form" data-pkg-detail-draft>
        @csrf @method('PUT')
        <input type="hidden" name="fingerprint" value="{{ $draftFingerprint }}">
        <label for="pkg-draft-title">Judul tampilan<input id="pkg-draft-title" name="title" maxlength="120" required value="{{ old('title', $draft->title) }}"></label>
        <label for="pkg-draft-summary">Ringkasan<textarea id="pkg-draft-summary" name="summary" maxlength="2000" rows="5" required>{{ old('summary', $draft->summary) }}</textarea></label>
        <label for="pkg-draft-benefits">Manfaat deskriptif <span class="pkg-hint">(satu baris per manfaat)</span><textarea id="pkg-draft-benefits" name="benefits" maxlength="2500" rows="7">{{ old('benefits', implode("\n", $draft->benefits ?? [])) }}</textarea></label>
        <div class="pkg-modal-buttons"><button type="button" id="pkg-detail-preview-open" class="button button-outline">Pratinjau perubahan</button><button type="submit" class="button button-primary">Simpan draft</button></div>
      </form>
      <section id="pkg-detail-preview" class="pkg-detail-preview" aria-label="Pratinjau perubahan belum tersimpan" hidden>
        <p class="pkg-detail-eyebrow">Pratinjau sementara — belum disimpan</p>
        <h3 id="pkg-detail-preview-title"></h3><p id="pkg-detail-preview-summary"></p><ul id="pkg-detail-preview-benefits"></ul>
      </section>
      <div class="pkg-detail-actions">
        <form method="POST" action="{{ route('admin.packages.publish', $plan) }}" class="pkg-detail-decision" data-pkg-detail-publish>
          @csrf<input type="hidden" name="fingerprint" value="{{ $draftFingerprint }}">
          <p><strong>Terbitkan draft tersimpan</strong></p><p class="pkg-hint">Hanya isi draft yang terakhir disimpan yang diterbitkan, bukan perubahan yang belum disimpan di form.</p>
          <label class="pkg-check"><input type="checkbox" name="confirmed" value="1" required> Saya sudah memeriksa draft tersimpan dan menyetujui penerbitan versi ini.</label>
          <p id="pkg-detail-unsaved" class="pkg-detail-unsaved" role="status" hidden>Ada perubahan di form yang belum disimpan. Simpan draft terlebih dahulu sebelum menerbitkan.</p>
          <button class="button button-primary" type="submit">Terbitkan versi ini</button>
        </form>
        <form method="POST" action="{{ route('admin.packages.discard', $plan) }}" class="pkg-detail-decision">
          @csrf<p><strong>Batalkan draft</strong></p><p class="pkg-hint">Tidak mengubah versi yang sedang terbit.</p>
          <label class="pkg-check"><input type="checkbox" name="confirmed" value="1" required> Saya mengonfirmasi pembatalan draft ini.</label>
          <button class="button button-outline" type="submit">Batalkan draft</button>
        </form>
      </div>
    @elseif ($published)
      <p>Belum ada draft revisi. Informasi pada versi terbit tetap aktif.</p>
      <form method="POST" action="{{ route('admin.packages.revision', $plan) }}" class="pkg-detail-form">
        @csrf<label class="pkg-check"><input type="checkbox" name="confirmed" value="1" required> Salin versi terbit saat ini untuk membuat draft revisi.</label>
        <button class="button button-primary" type="submit">Buat draft revisi</button>
      </form>
    @else
      <p class="pkg-hint">Belum ada draft yang dapat diedit. Periksa data paket sebelum melanjutkan.</p>
    @endif
  </section>
</div>
<div class="pkg-detail-bottom">
  <section class="admin-v1-panel pkg-detail-panel" aria-labelledby="pkg-availability-heading">
    <h2 id="pkg-availability-heading">Ketersediaan paket</h2>
    <p class="pkg-hint">Menentukan apakah peserta dapat mengajukan paket ini. Tidak mengubah keanggotaan peserta yang sudah ada.</p>
    <form method="POST" action="{{ route('admin.packages.availability', $plan) }}" class="pkg-detail-form">
      @csrf
      <label for="pkg-availability">Status pengajuan<select id="pkg-availability" name="availability">
        @foreach (['planned' => 'Dalam perencanaan', 'requestable' => 'Menerima pengajuan manual', 'closed' => 'Tutup pengajuan baru', 'archived' => 'Arsip / sembunyikan'] as $code => $label)
          <option value="{{ $code }}" @selected($plan->is_archived ? $code === 'archived' : $code === $plan->availability)>{{ $label }}</option>
        @endforeach
      </select></label>
      <label class="pkg-check"><input type="checkbox" name="confirmed" value="1" required> Saya mengonfirmasi perubahan ketersediaan paket.</label>
      <button class="button button-outline" type="submit">Simpan ketersediaan</button>
    </form>
  </section>
  <section class="admin-v1-panel pkg-detail-panel" aria-labelledby="pkg-history-heading">
    <h2 id="pkg-history-heading">Riwayat versi</h2><p class="pkg-hint">Versi lama tetap tersimpan untuk penelusuran. Hanya versi terbit saat ini yang terlihat di katalog publik.</p>
    <div class="pkg-detail-history"><table class="data-table admin-v1-table"><thead><tr><th>Versi</th><th>Judul</th><th>Status</th></tr></thead><tbody>
      @foreach ($versions as $version)
        <tr><td>v{{ $version->version_no }}</td><th scope="row">{{ $version->title }}</th><td>{{ $version->id === $plan->published_version_id ? 'Terbit saat ini' : match($version->status) { 'draft' => 'Draft', 'superseded' => 'Versi sebelumnya', 'published' => 'Terbit sebelumnya', 'discarded' => 'Draft dibatalkan', default => 'Riwayat' } }}</td></tr>
      @endforeach
    </tbody></table></div>
  </section>
</div>
@endsection
