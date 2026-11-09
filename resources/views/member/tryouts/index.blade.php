@extends('layouts.workspace')
@section('title','Tryout saya')
@section('content')
<div class="workspace-heading"><div><span class="workspace-label">TRYOUT ANGGOTA</span><h1>Tryout saya</h1><p>Latihan anggota dengan jawaban dan hasil tersimpan di server. Hanya template yang diterbitkan yang tersedia.</p></div></div>
@include('components.form-errors')
<p class="member-history-entry"><a class="text-link" href="{{ route('member.tryouts.history') }}">Lihat semua hasil dan progres tryout →</a></p>
@if($packages->isEmpty())
<section class="workspace-note"><strong>Belum ada tryout yang tersedia</strong><p>Pengelola belum menerbitkan template ujian. Kamu masih bisa mengerjakan demo SKD gratis.</p><a class="text-link" href="{{ route('practice.intro') }}">Coba demo SKD →</a></section>
@else
<div class="workspace-lines">
@foreach($packages as $package)
@php
  $history=$attempts->get($package->id,collect());
  $active=$history->first(fn($a)=>$a->status==='in_progress' && now()->lt($a->expires_at));
  $remaining=max(0,$package->max_attempts - $history->count());
@endphp
<article class="workspace-line member-package-line"><div><div class="member-eyebrow">{{ $package->code }} · {{ $package->items_count }} soal</div><h2>{{ $package->name }}</h2><p>{{ $package->description ?: 'Selesaikan soal dalam batas waktu yang disediakan.' }}</p><p>Durasi {{ $package->duration_minutes }} menit · Batas {{ $package->max_attempts }} percobaan · Sudah dimulai {{ $history->count() }} kali</p></div>
<div class="member-line-actions">
@if($active)
<a class="button button-primary" href="{{ route('member.tryouts.take',$active) }}">Lanjutkan sesi →</a>
@elseif($remaining>0)
<form method="POST" action="{{ route('member.tryouts.start',$package) }}">@csrf <button class="button button-primary" type="submit">Mulai tryout →</button></form>
@else
<span class="state-tag">Batas percobaan tercapai</span>
@endif
@foreach($history->filter(fn($a)=>$a->status!=='in_progress'||now()->gte($a->expires_at))->take(2) as $done)
<a class="text-link" href="{{ route('member.tryouts.result',$done) }}">Hasil percobaan {{ $done->attempt_number }} →</a>
@endforeach
</div></article>
@endforeach
</div>
@endif
<p class="member-disclaimer">Skor saat ini dihitung dari jumlah jawaban benar (1 poin tiap soal). Bukan penilaian CAT resmi BKN. Aturan TWK/TIU/TKP khusus akan disusun pada tahap berikutnya.</p>
@endsection
