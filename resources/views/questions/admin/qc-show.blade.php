@extends('layouts.admin')
@section('title','QC Soal — '.$question->code)
@section('content')
<div class="qi-page">
 <div class="admin-v1-heading qi-heading"><div><p class="admin-v1-crumb"><a href="{{ route('questions.qc.index') }}">QC Workspace</a> / {{ $question->code }}</p><h1>QC: {{ $question->code }}</h1><p>Versi {{ $version->version_number }} · {{ $version->review_status }} · Status QC <strong>{{ $review['status'] }}</strong></p></div><a class="button button-outline" href="{{ route('questions.admin.edit',$question) }}">Buka editor</a></div>
 <div class="qi-callout qi-callout-info" role="note">Hanya review internal. Hak media tidak diverifikasi otomatis; GP-02 tetap OPEN.</div>
 <section class="admin-v1-panel qi-panel"><h2>Versi yang sedang ditinjau</h2><p>{{ $version->body ?: 'Pertanyaan berbasis gambar' }}</p><ul>
 @foreach($version->options as $option)
 <li>{{ $option->label }}. {{ $option->body ?: '(gambar)' }} @if($option->is_correct) <strong>(kunci)</strong> 
@endif
</li>
 
@endforeach

 </ul><p><strong>Pembahasan:</strong> {{ $version->explanation ?: '(gambar)' }}</p>
 @foreach($version->media as $media)
 <figure><img class="qi-qc-media-image" src="{{ route('questions.admin.media.preview',[$question,$media]) }}" alt="{{ $media->alt_text ?: 'Gambar soal dalam peninjauan' }}" loading="lazy"><figcaption>{{ $media->slot }} #{{ $media->position }} · {{ $media->caption }}</figcaption></figure>
 
@endforeach

 <p class="qi-muted">Hash substansi: <code>{{ $review['hash'] }}</code></p></section>
 <section class="admin-v1-panel qi-panel"><h2>Keputusan internal untuk hash ini</h2>
 @if($review['status']==='STALE')<p role="alert">Konten atau media berubah sejak review terakhir. Tinjau ulang sebelum menyimpan keputusan.</p>
@endif

 <form method="POST" action="{{ route('questions.qc.mark',$question) }}">
@csrf

 <input type="hidden" name="hash" value="{{ $review['hash'] }}">
 <label for="qc-decision">Keputusan</label><select id="qc-decision" name="decision" required><option value="NEEDS_REVISION">Perlu revisi</option><option value="REVIEWED_INTERNAL">Sudah ditinjau internal</option></select>
 <label for="qc-notes">Catatan reviewer</label><textarea id="qc-notes" name="notes" maxlength="500" rows="3">{{ old('notes',$review['notes']) }}</textarea>
 @if($errors->any())<div role="alert">{{ $errors->first() }}</div>
@endif

 <button type="submit" class="button button-primary">Simpan QC versi ini</button></form></section>
</div>
@endsection
