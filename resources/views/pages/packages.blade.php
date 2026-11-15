@extends('layouts.public')
@section('title','Pilihan paket')
@section('content')
<section class="task-heading"><div class="container"><div class="breadcrumbs"><a href="{{ route('home') }}">Beranda</a> / Paket</div><h1>Pilih jalur belajar yang sesuai</h1><p>Bandingkan informasi paket yang diterbitkan. Konfirmasi perubahan paket dilakukan bersama admin; tidak ada checkout otomatis.</p></div></section>
<section class="task-body pkg-public"><div class="container">
  @if($current)<p class="pkg-current">Paket akun Anda saat ini: <strong>{{ $current->publishedVersion?->title ?? $current->name }}</strong></p>@endif
  @if($pending)<p class="pkg-notice">Ada pengajuan yang belum diproses: <a href="{{ route('member.packages.request', $pending) }}">{{ $pending->reference }}</a>. Pengajuan lain dapat dibuat setelah pengajuan ini diselesaikan.</p>@endif
  @forelse($catalog as $plan)
    @php($version = $plan->publishedVersion)
    @if($version)
    <article class="pkg-offer" aria-labelledby="pkg-offer-{{ $plan->id }}">
      <div class="pkg-offer-index"><span class="eyebrow">{{ $plan->availability === 'requestable' ? 'PILIHAN PAKET' : ($plan->availability === 'planned' ? 'DALAM PERENCANAAN' : 'PENAWARAN DITUTUP') }}</span><span class="pkg-code">{{ $plan->code }}</span></div>
      <div class="pkg-offer-main"><h2 id="pkg-offer-{{ $plan->id }}">{{ $version->title }}</h2><p>{{ $version->summary }}</p>
        @if(count($version->benefits ?? []))<ul class="pkg-benefits">@foreach($version->benefits as $benefit)<li>{{ $benefit }}</li>@endforeach</ul>@endif
        <p class="pkg-policy-note">Kuota Quiz Kilat, jika tersedia, mengikuti kebijakan paket yang benar-benar aktif. Katalog ini tidak otomatis membuka CAT atau Kelas.</p>
      </div>
      <div class="pkg-offer-action">
        @if($plan->availability !== 'requestable')
          <span class="pkg-muted">{{ $plan->availability === 'planned' ? 'Belum tersedia untuk pengajuan' : 'Tidak menerima pengajuan baru' }}</span>
        @elseif($current && $current->id === $plan->id)
          <strong class="pkg-current-label">Paket Anda</strong>
        @elseif($current && $pending)
          <span class="pkg-muted">Selesaikan pengajuan sebelumnya</span>
        @elseif($current)
          <form method="POST" action="{{ route('member.packages.request.store', $plan) }}">@csrf
            <label class="pkg-check"><input type="checkbox" name="confirmed" value="1" required> Saya mengerti pengajuan ini belum mengubah paket atau membuktikan pembayaran.</label>
            <button class="button button-primary" type="submit">Ajukan perubahan paket</button>
          </form>
        @elseif(auth()->guest())
          <a class="button button-outline" href="{{ route('login') }}">Masuk untuk mengajukan</a>
        @else
          <span class="pkg-muted">Pengajuan hanya untuk peserta aktif</span>
        @endif
      </div>
    </article>
    @endif
  @empty
    <div class="pkg-empty"><h2>Belum ada informasi paket yang diterbitkan</h2><p>Silakan gunakan latihan contoh yang saat ini tersedia.</p><a class="text-link" href="{{ route('practice.intro') }}">Buka latihan gratis</a></div>
  @endforelse
  <p class="pkg-legal">Harga, durasi, dan manfaat komersial hanya berlaku setelah disepakati secara manual dengan admin. WhatsApp bukan sistem pembayaran atau bukti verifikasi.</p>
</div></section>
@endsection
