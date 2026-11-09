@extends('layouts.admin')
@section('title','Detail Registrasi')
@section('content')
<div class="admin-page-head"><div><a class="text-link admin-breadcrumb" href="{{ route('registration.index') }}">← Kembali ke registrasi</a><h1>Detail registrasi</h1><p>Periksa data dan catatan konfirmasi melalui kanal resmi.</p></div></div>
<section class="admin-section admin-detail"><dl class="status-details"><dt>Referensi</dt><dd>{{ $registration->reference_code }}</dd><dt>Nama</dt><dd>{{ $user->name }}</dd><dt>Email</dt><dd>{{ $user->email }}</dd><dt>WhatsApp</dt><dd>{{ $user->whatsapp_number }}</dd><dt>Status</dt><dd><span class="state-tag">{{ $registration->status }}</span></dd><dt>Waktu daftar</dt><dd>{{ $registration->submitted_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</dd></dl></section>
@if($registration->status === 'pending')<section class="admin-section admin-detail"><h2>Ambil keputusan</h2><p>Hanya lakukan tindakan setelah konfirmasi eksternal diperiksa.</p><form class="form-stack" method="POST" action="{{ route('registration.decide', $user) }}">@csrf
<label>Keputusan<select name="decision" required><option value="">Pilih keputusan</option><option value="approve">Setujui &amp; aktifkan akun</option><option value="reject">Tolak pendaftaran</option></select></label>
<label>Referensi konfirmasi WhatsApp (opsional)<input name="external_reference" maxlength="120" value="{{ old('external_reference') }}" placeholder="Tanggal percakapan / referensi internal"></label>
<label>Catatan internal (min. 10 karakter)<textarea name="admin_note" rows="4" required maxlength="1000" placeholder="Tuliskan hasil pemeriksaan dan alasan keputusan.">{{ old('admin_note') }}</textarea></label>
<label class="check-line"><input type="checkbox" name="confirmed_external" value="1" required><span>Saya telah memeriksa konfirmasi eksternal. Keputusan ini akan tercatat dalam audit log.</span></label>
<button class="button button-primary" type="submit">Simpan keputusan</button>
</form></section>@else<section class="admin-section admin-detail"><p>Pendaftaran diproses pada {{ $registration->reviewed_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB.</p></section>@endif
@endsection
