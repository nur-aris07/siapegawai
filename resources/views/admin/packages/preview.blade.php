@extends('layouts.admin')
@section('title','Pratinjau paket')
@section('content')
<div class="admin-v1-heading"><div><p class="admin-v1-crumb">Katalog / Pratinjau</p><h1>Pratinjau draft {{ $plan->code }}</h1><p>Pratinjau ini tidak mengubah versi published atau menyimpan draft.</p></div></div>
<section class="admin-v1-panel"><p class="pkg-hint">Draf yang sedang ditinjau</p><h2>{{ $title }}</h2><p>{{ $summary }}</p><ul>@foreach($benefits as $benefit)<li>{{ $benefit }}</li>@endforeach</ul>
  <p class="pkg-hint">Saat ini terbit: {{ $published?->title ?? 'Belum ada' }}. Kembali ke halaman pengelolaan untuk menyimpan dan menerbitkan.</p>
  <a href="{{ route('admin.packages.show', $plan) }}" class="button button-outline">Kembali</a>
</section>
@endsection
