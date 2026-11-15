@extends('layouts.workspace')
@section('title','Pengajuan paket')
@section('content')
<div class="workspace-heading"><div><span class="workspace-label">AKUN PESERTA</span><h1>Pengajuan perubahan paket</h1><p>Informasi ini hanya dapat diakses oleh pemilik pengajuan.</p></div></div>
<section class="pkg-member-request"><p><strong>Referensi:</strong> {{ $upgrade->reference }}</p>
  <p><strong>Status:</strong> {{ $upgrade->status === 'pending' ? 'Menunggu verifikasi manual' : ($upgrade->status === 'completed' ? 'Selesai' : 'Ditolak') }}</p>
  <p><strong>Paket asal:</strong> {{ $upgrade->sourcePlan->name }}</p>
  <p><strong>Paket tujuan:</strong> {{ $upgrade->targetVersion->title }} (versi {{ $upgrade->targetVersion->version_no }})</p>
  <p>Permintaan dan komunikasi WhatsApp tidak mengubah hak akses secara otomatis. Admin akan memproses setelah verifikasi manual.</p>
  @if($upgrade->status === 'pending' && $whatsappUrl)
    <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer" class="button button-primary">Lanjutkan lewat WhatsApp resmi</a>
  @elseif($upgrade->status === 'pending')
    <p class="pkg-notice">Nomor WhatsApp resmi belum terkonfigurasi. Pengajuan tetap tercatat, tetapi jangan mengirim dokumen pribadi melalui kanal yang tidak terverifikasi.</p>
  @endif
  <p><a class="text-link" href="{{ route('page.paket') }}">Kembali ke katalog paket</a></p>
</section>
@endsection
