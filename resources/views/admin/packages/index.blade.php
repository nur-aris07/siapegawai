@extends('layouts.admin')
@section('title','Katalog paket')
@section('content')
<div class="admin-v1-heading"><div><p class="admin-v1-crumb">Operasional / Paket</p><h1>Katalog paket</h1><p>Kelola identitas, revisi terbit, ketersediaan, dan pengajuan peserta.</p></div></div>
<section class="admin-v1-panel pkg-admin-panel" data-pkg-table data-url="{{ route('admin.packages.index') }}">
  <div class="pkg-admin-toolbar"><div><h2>Daftar paket</h2><p>Perubahan draft tidak mengganti versi yang sedang terbit.</p></div>
    <div class="pkg-admin-actions"><a class="button button-outline" href="{{ route('admin.packages.ordering') }}">Atur urutan</a><a class="button button-outline" href="{{ route('admin.packages.requests') }}">Antrean upgrade</a>
      <button class="button button-primary" id="pkg-create-open" type="button">Tambah paket</button></div></div>
  <div class="pkg-admin-filters"><label for="pkg-search">Cari paket<input id="pkg-search" type="search" placeholder="Cari nama atau kode" autocomplete="off"></label>
    <label for="pkg-size">Tampilkan<select id="pkg-size"><option value="10">10</option><option value="25">25</option><option value="50">50</option></select></label>
    <button type="button" id="pkg-refresh" class="button button-outline button-compact">Muat ulang</button></div>
  <p id="pkg-table-error" role="alert" hidden></p>
  <div class="table-responsive"><table class="data-table admin-v1-table pkg-admin-table" aria-label="Daftar jenis paket">
    <thead><tr><th>Paket</th><th>Kode</th><th>Urutan</th><th>Penawaran</th><th>Versi</th><th>Tindakan</th></tr></thead>
    <tbody id="pkg-rows">@foreach($plans as $plan)<tr><th scope="row">{{ $plan->publishedVersion?->title ?? $plan->name }}</th><td>{{ $plan->code }}</td><td>{{ $plan->tier }}</td><td>{{ $plan->is_archived ? 'Diarsipkan' : match($plan->availability) { 'requestable' => 'Menerima pengajuan', 'closed' => 'Pengajuan ditutup', default => 'Dalam perencanaan' } }}</td><td>{{ $plan->publishedVersion?->version_no ?? 'Draft' }}</td><td><a class="pkg-manage-link" href="{{ route('admin.packages.show',$plan) }}">Kelola</a></td></tr>@endforeach</tbody>
  </table></div>
  <div class="pkg-admin-footer"><span id="pkg-table-info">{{ $plans->count() }} paket</span><div><button type="button" id="pkg-prev" disabled>Sebelumnya</button><button type="button" id="pkg-next" disabled>Berikutnya</button></div></div>
</section>
<section class="admin-v1-panel pkg-admin-panel"><div class="pkg-admin-toolbar"><div><h2>Pengajuan terbaru</h2><p>Komunikasi dan pembayaran di luar sistem. Hanya superadmin dapat menyelesaikan perubahan paket.</p></div>
  <a href="{{ route('admin.packages.requests') }}" class="button button-outline">Lihat semua pengajuan</a></div>
  @forelse($requests as $upgrade)
  <p class="pkg-request-line"><strong>{{ $upgrade->reference }}</strong> · {{ $upgrade->participant->name }} · {{ $upgrade->sourcePlan->name }} → {{ $upgrade->targetVersion->title }} (v{{ $upgrade->targetVersion->version_no }})</p>
  @empty<p>Belum ada pengajuan yang menunggu verifikasi.</p>@endforelse
</section>
<dialog id="pkg-create-dialog" class="pkg-modal" aria-labelledby="pkg-create-title">
  <div class="pkg-modal-head"><div><h2 id="pkg-create-title">Tambah paket</h2><p>Identitas baru dibuat sebagai draft, tidak langsung bisa diajukan.</p></div><button type="button" data-pkg-close> Tutup </button></div>
  <form method="POST" action="{{ route('admin.packages.store') }}" class="pkg-modal-content">@csrf
    <label>Kode paket<input name="code" required maxlength="32" pattern="[A-Z][A-Z0-9_]{1,31}" placeholder="PAKET_BARU"></label>
    <label>Nama paket<input name="name" required maxlength="80"></label>
    <label>Jenis<select name="is_paid"><option value="1">Berbayar / manual</option><option value="0">Gratis</option></select></label>
    <p class="pkg-hint">Paket baru otomatis ditambahkan pada urutan terakhir. Gunakan <strong>Atur urutan</strong> untuk mengubah posisi tampil.</p>
    <label>Ringkasan<textarea name="summary" required maxlength="2000" rows="3"></textarea></label>
    <label>Manfaat deskriptif (satu baris per item)<textarea name="benefits" maxlength="2500" rows="4"></textarea></label>
    <p class="pkg-hint">Manfaat ini tidak memberikan privilege otomatis. Kuota QQ dikelola melalui kebijakan paket; CAT dan Kelas tetap menggunakan hak terpisah.</p>
    <div class="pkg-modal-buttons"><button class="button button-primary" type="submit">Buat draft paket</button><button type="button" data-pkg-close class="button button-outline">Batal</button></div>
  </form>
</dialog>
@endsection
