@extends('layouts.admin')
@section('title','Kelola kelas')
@section('content')
<div class="admin-v1-heading"><div><p class="admin-v1-crumb">Pembelajaran / Kelas</p><h1>Kelola kelas</h1><p>Buat kelas draft, terbitkan materi, lalu atur akses peserta secara manual.</p></div></div>
<div class="admin-v1-catalog-grid">
<section class="admin-v1-panel"><h2>Daftar kelas</h2><div class="table-scroll"><table class="data-table admin-v1-table"><thead><tr><th>Judul</th><th>Status</th><th>Materi</th><th>Peserta aktif</th><th>Aksi</th></tr></thead><tbody>
@forelse($classes as $class)
<tr><td>{{ $class->title }}</td><td>{{ ['draft'=>'Draft','open'=>'Dibuka','closed'=>'Ditutup'][$class->status] ?? $class->status }}</td><td>{{ $class->materials_count }}</td><td>{{ $class->enrollments_count }}</td><td><a href="{{ route('admin.classes.edit',$class) }}">Kelola</a></td></tr>
@empty<tr><td colspan="5">Belum ada kelas. Tambahkan kelas pertama melalui formulir.</td></tr>@endforelse
</tbody></table></div>{{ $classes->links() }}</section>
<section class="admin-v1-panel"><h2>Buat kelas draft</h2><p>Kelas baru tidak langsung terlihat peserta.</p>
<form action="{{ route('admin.classes.store') }}" method="POST" class="admin-v1-action-form">@csrf
<label class="admin-v1-field">Nama kelas<input name="title" value="{{ old('title') }}" minlength="5" maxlength="160" required></label>
<label class="admin-v1-field">Deskripsi<textarea name="description" rows="4" minlength="15" maxlength="3000" required>{{ old('description') }}</textarea></label>
<label class="admin-v1-field">Silabus (opsional)<textarea name="syllabus" rows="4" maxlength="6000">{{ old('syllabus') }}</textarea></label>
<label class="admin-v1-field">Kapasitas (opsional)<input name="capacity" type="number" min="1" max="100000" value="{{ old('capacity') }}"></label>
<button class="button button-primary" type="submit">Buat kelas draft</button></form></section>
</div>
@endsection
