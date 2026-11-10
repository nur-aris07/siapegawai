@extends('layouts.admin')
@section('title', $practice ? 'Kelola Latihan Kelas' : 'Buat Latihan Kelas')
@section('content')
<div class="admin-v1-heading"><div><p class="admin-v1-crumb"><a href="{{ route('admin.classes.edit',$learningClass) }}">Kelas</a> / <a href="{{ route('admin.class-practices.index',$learningClass) }}">Latihan</a></p><h1>{{ $practice?->title ?? 'Buat Latihan Kelas' }}</h1><p>Latihan kelas menggunakan timer sesi dan versi soal yang dibekukan saat terbit.</p></div></div>
@if(!$practice || $practice->status==='draft')
<form method="POST" action="{{ $practice ? route('admin.class-practices.update',[$learningClass,$practice]) : route('admin.class-practices.store',$learningClass) }}" class="admin-v1-tryout-editor" data-tryout-editor data-search-url="{{ route('admin.class-practices.search',$learningClass) }}">
@csrf @if($practice) @method('PUT') @endif
<section class="admin-v1-panel admin-v1-form-panel"><h2>Pengaturan latihan</h2>
<label class="admin-v1-field">Judul<input name="title" required minlength="5" maxlength="160" value="{{ old('title',$practice?->title) }}"></label>
<label class="admin-v1-field">Deskripsi<textarea name="description" rows="3" maxlength="2500">{{ old('description',$practice?->description) }}</textarea></label>
<div class="admin-v1-editor-row">
<label class="admin-v1-field">Durasi (menit, 5–180)<input name="duration_minutes" type="number" min="5" max="180" required value="{{ old('duration_minutes',$practice?->duration_minutes ?? 30) }}"></label>
<label class="admin-v1-field">Maksimum percobaan (1–5)<input name="max_attempts" type="number" min="1" max="5" required value="{{ old('max_attempts',$practice?->max_attempts ?? 1) }}"></label>
</div>
<div class="admin-v1-editor-row"><label class="admin-v1-field">Materi terkait (opsional)<select name="material_id"><option value="">Tidak terikat materi khusus</option>
@foreach($materials as $material)<option value="{{ $material->id }}" @selected((string)old('material_id',$practice?->material_id)===(string)$material->id)>{{ $material->title }}</option>@endforeach
</select></label>
<label class="admin-v1-field">Urutan<input type="number" name="sort_order" min="0" max="65535" required value="{{ old('sort_order',$practice?->sort_order ?? 0) }}"></label></div>
</section>
<section class="admin-v1-panel admin-v1-form-panel"><h2>Pilih versi soal</h2><p>Hanya soal terbit. Urutan disimpan sesuai daftar pilihan. Maksimal 100 soal.</p>
<div class="admin-v1-tryout-search"><label class="admin-v1-field">Cari kode atau pertanyaan<input type="search" id="tryout-question-search" placeholder="TWK-001" autocomplete="off"></label>
<label class="admin-v1-field">Kategori<select id="tryout-question-subject"><option value="">Semua kategori</option>@foreach($subjects as $subject)<option value="{{ $subject->code }}">{{ $subject->code }}</option>@endforeach</select></label>
<button type="button" class="button button-outline button-compact" id="tryout-search-button">Cari soal</button></div>
<p id="tryout-search-message" class="admin-v1-muted" role="status" aria-live="polite">Cari soal dari bank terbit.</p><div id="tryout-search-results" class="admin-v1-tryout-results"></div>
<h3 class="admin-v1-tryout-subheading">Soal dipilih (<span id="tryout-selected-count">{{ $selected->count() }}</span>/100)</h3>
<ol id="tryout-selected-list" class="admin-v1-selected-list">
@foreach($selected as $row)
<li data-version-id="{{ $row['version_id'] }}"><span>{{ $row['code'] }} · {{ $row['subject'] }} · v{{ $row['version'] }} — {{ $row['summary'] }}</span><span class="admin-v1-selected-actions"><button type="button" data-move="up" class="button button-outline button-compact">Naik</button><button type="button" data-move="down" class="button button-outline button-compact">Turun</button><button type="button" data-remove class="button button-outline button-compact">Hapus</button></span><input type="hidden" name="version_ids[]" value="{{ $row['version_id'] }}"></li>
@endforeach
</ol></section>
<div class="admin-v1-editor-actions"><button class="button button-primary" type="submit">{{ $practice ? 'Simpan draft' : 'Buat draft' }}</button><a class="button button-outline" href="{{ route('admin.class-practices.index',$learningClass) }}">Kembali</a></div>
</form>
@else
<section class="admin-v1-panel"><h2>Latihan {{ $practice->status==='published'?'terbit':'diarsipkan' }}</h2><p>Konten sudah dibekukan dan tidak bisa diubah. Buat latihan baru untuk revisi.</p>
<p>Durasi {{ $practice->duration_minutes }} menit · Maksimal {{ $practice->max_attempts }} percobaan · {{ $practice->items()->count() }} soal.</p>
<ol>@foreach($practice->items()->with('version.question')->get() as $item)<li>{{ $item->version?->question?->code }} · v{{ $item->version?->version_number }} — {{ \Illuminate\Support\Str::limit($item->version?->body,100) }}</li>@endforeach</ol></section>
@if($practice->status==='published')
<section class="admin-v1-panel"><h2>Arsipkan latihan</h2><form method="POST" class="admin-v1-action-form" action="{{ route('admin.class-practices.archive',[$learningClass,$practice]) }}">@csrf
<label class="admin-v1-field">Alasan arsip (min. 10 karakter)<textarea name="reason" required minlength="10" maxlength="500" rows="2"></textarea></label><button type="submit" class="button button-outline">Arsipkan tanpa menghapus histori</button></form></section>
@endif
@endif
@if($practice && $practice->status==='draft')
<section class="admin-v1-panel"><h2>Publikasi latihan</h2><p>Pastikan soal, kunci, dan pembahasan sudah benar. Setelah terbit, versi soal tidak bisa diganti.</p>
<form method="POST" action="{{ route('admin.class-practices.publish',[$learningClass,$practice]) }}">@csrf
<label class="check-line"><input type="checkbox" name="confirmed_review" value="1" required> Saya sudah meninjau soal dan aturan latihan.</label>
<button class="button button-primary" type="submit">Terbitkan Latihan Kelas</button></form></section>
@endif
@endsection
