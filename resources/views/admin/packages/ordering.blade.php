@extends('layouts.admin')
@section('title', 'Atur urutan paket')
@section('content')
<div class="admin-v1-heading"><div><p class="admin-v1-crumb">Operasional / <a href="{{ route('admin.packages.index') }}">Katalog paket</a> / Urutan</p>
<h1>Atur urutan paket</h1><p>Posisi pertama ditampilkan lebih dahulu di katalog publik dan daftar admin.</p></div></div>
<section class="admin-v1-panel pkg-order-panel" data-pkg-ordering>
  <div class="pkg-admin-toolbar"><div><h2>Urutan tampilan</h2><p>Seret lewat pegangan di desktop; pada ponsel gunakan tombol Naik dan Turun. Perubahan belum berlaku sampai disimpan.</p></div>
    <a class="button button-outline" href="{{ route('admin.packages.index') }}">Kembali ke katalog</a></div>
  <form method="POST" action="{{ route('admin.packages.ordering.save') }}" id="pkg-order-form">@csrf
    <input type="hidden" name="fingerprint" value="{{ $fingerprint }}">
    <ol id="pkg-sort-list" class="pkg-sort-list" aria-label="Urutan seluruh paket">
      @foreach($plans as $plan)
      <li data-pkg-sort-item data-id="{{ $plan->id }}" draggable="true">
        <input type="hidden" name="order[]" value="{{ $plan->id }}">
        <span class="pkg-sort-grip" draggable="false" aria-hidden="true">⠿</span>
        <span class="pkg-sort-rank" aria-hidden="true">{{ $loop->iteration }}</span>
        <span class="pkg-sort-label"><strong>{{ $plan->publishedVersion?->title ?? $plan->name }}</strong><small>{{ $plan->code }}{{ $plan->is_archived ? ' · Diarsipkan' : '' }}</small></span>
        <span class="pkg-sort-controls"><button type="button" data-pkg-move="up" aria-label="Naikkan {{ $plan->code }}" @disabled($loop->first)>Naik <span aria-hidden="true">↑</span></button>
          <button type="button" data-pkg-move="down" aria-label="Turunkan {{ $plan->code }}" @disabled($loop->last)>Turun <span aria-hidden="true">↓</span></button></span>
      </li>
      @endforeach
    </ol>
    <p id="pkg-order-live" class="sr-only" aria-live="polite" aria-atomic="true"></p>
    <div class="pkg-order-footer"><label class="pkg-check"><input type="checkbox" name="confirmed" value="1" required> Saya mengonfirmasi urutan ini untuk katalog publik dan admin.</label>
      <div class="pkg-modal-buttons"><button type="submit" id="pkg-order-save" class="button button-primary" disabled>Simpan urutan</button><a class="button button-outline" href="{{ route('admin.packages.index') }}">Batal</a></div></div>
    <noscript><p class="pkg-hint">Pengurutan interaktif membutuhkan JavaScript. Tidak ada perubahan yang disimpan tanpa interaksi.</p></noscript>
  </form>
</section>
@endsection
