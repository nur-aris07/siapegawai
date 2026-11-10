@extends('layouts.admin')
@section('title','Latihan Kelas')
@section('content')
<div class="admin-v1-heading"><div><p class="admin-v1-crumb"><a href="{{ route('admin.classes.edit',$learningClass) }}">Kelas</a> / Latihan</p><h1>Latihan Kelas — {{ $learningClass->title }}</h1><p>Susun soal dari bank terbit, review, lalu terbitkan. Riwayat peserta tetap terpisah dari CAT.</p></div><a href="{{ route('admin.class-practices.create',$learningClass) }}" class="button button-primary">Buat latihan</a></div>
<section class="admin-v1-panel"><h2>Daftar latihan</h2><div class="workspace-lines">
@forelse($practices as $practice)
<article class="workspace-line"><div><strong>{{ $practice->title }}</strong><p>{{ ['draft'=>'Draft','published'=>'Terbit','archived'=>'Arsip'][$practice->status] ?? $practice->status }} · {{ $practice->items_count }} soal · {{ $practice->duration_minutes }} menit · Maks. {{ $practice->max_attempts }} percobaan</p></div><a class="button button-outline button-compact" href="{{ route('admin.class-practices.edit',[$learningClass,$practice]) }}">Detail</a></article>
@empty
<p class="admin-v1-muted">Belum ada latihan di kelas ini.</p>
@endforelse
</div></section>
@endsection
