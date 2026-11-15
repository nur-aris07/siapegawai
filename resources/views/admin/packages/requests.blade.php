@extends('layouts.admin')
@section('title','Pengajuan paket')
@section('content')
<div class="admin-v1-heading"><div><p class="admin-v1-crumb">Operasional / Paket</p><h1>Pengajuan perubahan paket</h1><p>Periksa komunikasi dan konfirmasi pembayaran di luar aplikasi sebelum memproses.</p></div></div>
<section class="admin-v1-panel"><a href="{{ route('admin.packages.index') }}" class="text-link">Kembali ke katalog</a>
@forelse($requests as $upgrade)
<article class="pkg-admin-request"><div><strong>{{ $upgrade->reference }}</strong><p>Peserta: {{ $upgrade->participant->name }} · {{ $upgrade->participant->email }}</p>
<p>{{ $upgrade->sourcePlan->name }} → {{ $upgrade->targetVersion->title }} (versi {{ $upgrade->targetVersion->version_no }})</p>
<p>Status: {{ $upgrade->status }} · Dibuat: {{ $upgrade->created_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</p>
@if($upgrade->targetPlan->published_version_id !== $upgrade->target_published_version_id)<p class="pkg-notice">Versi yang dipilih peserta berbeda dari versi terbaru. Tinjau isi pengajuan sebelum memproses.</p>@endif
</div>
@if($upgrade->status === 'pending')<form method="POST" action="{{ route('admin.packages.requests.decide',$upgrade) }}" class="pkg-decision">@csrf
  <label>Keputusan<select name="decision" required><option value="">Pilih tindakan</option><option value="completed">Tandai selesai &amp; tetapkan paket</option><option value="rejected">Tolak pengajuan</option></select></label>
  <label>Alasan dan hasil verifikasi manual<textarea name="reason" minlength="12" maxlength="500" rows="2" required></textarea></label>
  <label class="pkg-check"><input type="checkbox" name="manual_verified" value="1"> Untuk keputusan selesai: saya telah memverifikasi kesepakatan dan pembayaran di luar sistem</label>
  <label class="pkg-check"><input type="checkbox" name="confirmed" value="1" required> Konfirmasi eksekusi keputusan</label>
  <button class="button button-primary" type="submit">Proses keputusan</button>
</form>@endif
</article>
@empty<p>Belum ada pengajuan.</p>@endforelse
{{ $requests->links() }}
</section>
@endsection
