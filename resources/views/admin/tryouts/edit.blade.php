@extends('layouts.admin')
@section('title','Kelola Tryout '.$package->code)
@section('content')
<div class="admin-v1-heading"><div><p class="admin-v1-crumb"><a href="{{ route('tryouts.admin.index') }}">Tryout</a> / {{ $package->code }}</p><h1>{{ $package->name }}</h1><p><strong>{{ strtoupper($package->status) }}</strong> · {{ $package->items->count() }} soal · {{ $package->duration_minutes }} menit · Maks. {{ $package->max_attempts }} percobaan.</p></div></div>
@if($package->status==='draft')
  @include('admin.tryouts.form',['action'=>route('tryouts.admin.update',$package),'method'=>'PUT'])
  <section class="admin-v1-panel admin-v1-form-panel admin-v1-tryout-decision"><h2>Review dan terbitkan</h2><p>Terbitkan hanya setelah memeriksa urutan, versi soal, durasi, dan batas percobaan. Susunan paket tidak dapat diubah langsung setelah publikasi.</p>
  <form method="POST" action="{{ route('tryouts.admin.publish',$package) }}" class="admin-v1-action-form">@csrf
    <label class="check-line"><input type="checkbox" name="confirmed_review" value="1" required> Saya telah memeriksa susunan dan pengaturan template tryout.</label>
    <button type="submit" class="button button-primary button-compact">Terbitkan template</button>
  </form></section>
@else
  <section class="admin-v1-panel admin-v1-form-panel"><h2>Versi soal yang dikunci</h2><p>Susunan ini tidak dapat diubah setelah publikasi. Revisi bank soal tidak memindahkan versi yang ditampilkan di sini.</p>
    <div class="admin-v1-pinned-list">@forelse($selected as $i=>$row)<div class="admin-v1-pinned-row"><strong>{{ $i+1 }}.</strong><span>{{ $row['code'] }} · {{ $row['subject'] }} · v{{ $row['version'] }}</span><small>{{ $row['summary'] }}</small></div>@empty<p>Belum ada soal.</p>@endforelse</div>
  </section>
  @if($package->status==='published')<section class="admin-v1-panel admin-v1-form-panel admin-v1-tryout-decision"><h2>Arsipkan template</h2><form method="POST" action="{{ route('tryouts.admin.archive',$package) }}" class="admin-v1-action-form">@csrf
    <label class="admin-v1-field">Alasan pengarsipan<textarea name="reason" required minlength="10" maxlength="500" rows="2"></textarea></label><button type="submit" class="button button-outline button-compact">Arsipkan tryout</button>
  </form></section>@endif
@endif
@endsection
