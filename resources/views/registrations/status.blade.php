@extends('layouts.account')
@section('title','Status Aktivasi')
@section('content')
<section class="account-panel account-panel-wide"><div class="account-heading"><span class="account-kicker">PENDAFTARAN</span><h1>Status pendaftaran</h1><p>Status ini berasal dari sistem, bukan dari aktivitas pengiriman pesan WhatsApp.</p></div><dl class="status-details"><dt>Status</dt><dd><span class="state-tag">{{ match(auth()->user()->status) {'pending'=>'Menunggu konfirmasi admin','active'=>'Aktif','suspended'=>'Ditangguhkan','rejected'=>'Tidak disetujui',default=>'Tidak diketahui'} }}</span></dd><dt>Kode referensi</dt><dd><strong>{{ $registration->reference_code }}</strong></dd></dl>
@if(auth()->user()->status === 'active')<a class="button button-primary" href="{{ route('dashboard') }}">Masuk dashboard</a>
@elseif(auth()->user()->status === 'pending')<p>Silakan lanjutkan konfirmasi melalui WhatsApp resmi. Admin akan memeriksa pendaftaran secara manual.</p>
@else<p>Jika memerlukan penjelasan, hubungi pengelola melalui kanal resmi. Detail keputusan internal tidak ditampilkan.</p>@endif
@if($whatsappUrl)<p class="account-actions"><a class="button button-outline" href="{{ $whatsappUrl }}" rel="noopener noreferrer">Hubungi admin via WhatsApp</a></p>@endif
</section>
@endsection
