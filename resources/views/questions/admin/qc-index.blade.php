@extends('layouts.admin')
@section('title','Content Studio — QC Soal')
@section('content')
<div class="qi-page">
 <div class="admin-v1-heading qi-heading"><div><p class="admin-v1-crumb"><a href="{{ route('questions.admin.index') }}">Bank Soal</a> / Content Studio</p><h1>QC Workspace</h1><p>Review internal terikat hash versi soal. Tidak mengubah status publikasi, hak media atau GP-02.</p></div><a class="button button-outline" href="{{ route('questions.import.index') }}">Impor soal bergambar</a></div>
 <div class="qi-callout qi-callout-info" role="note">Status REVIEWED INTERNAL bukan persetujuan akademik independen. Soal dengan perubahan teks atau gambar akan berstatus STALE.</div>
 <form class="qi-qc-filters" method="GET" action="{{ route('questions.qc.index') }}" aria-label="Cari dan filter status QC">
   <label for="qc-code">Kode soal</label><input id="qc-code" name="code" maxlength="48" value="{{ $code }}" placeholder="Contoh: TIU-001">
   <label for="qc-topic">Topik</label><select id="qc-topic" name="topic_id"><option value="">Semua topik</option>
   @foreach($topics as $topic)
   <option value="{{ $topic->id }}" @selected($topicId===$topic->id)>{{ $topic->name }}</option>
   
@endforeach

   </select>
   <label for="qc-status">Status QC</label><select id="qc-status" name="qc_status"><option value="">Semua status</option>
   @foreach(['UNREVIEWED'=>'Belum ditinjau','NEEDS_REVISION'=>'Perlu revisi','REVIEWED_INTERNAL'=>'Ditinjau internal','STALE'=>'Sudah usang'] as $value=>$label)
   <option value="{{ $value }}" @selected($status===$value)>{{ $label }}</option>
   
@endforeach

   </select>
   <button class="button button-primary" type="submit">Terapkan filter</button>
   <a class="button button-outline" href="{{ route('questions.qc.index') }}">Reset</a>
 </form>
 <section class="admin-v1-panel qi-panel"><div class="table-scroll" tabindex="0" aria-label="Daftar QC soal"><table class="data-table admin-v1-table qi-table"><thead><tr><th>Kode</th><th>Versi</th><th>Status QC</th><th>Editorial</th><th>Aksi</th></tr></thead><tbody>
 @forelse($rows as $row)
 <tr><td>{{ $row['question']->code }}</td><td>{{ $row['version']?->version_number ?? '—' }}</td><td>{{ $row['qc']['status'] ?? 'UNREVIEWED' }}</td><td>{{ $row['version']?->review_status ?? '—' }}</td><td><a href="{{ route('questions.qc.show',$row['question']) }}">Tinjau</a></td></tr>
 @empty
 <tr><td colspan="5">Tidak ditemukan soal sesuai filter.</td></tr>
 
@endforelse

 </tbody></table></div>{{ $questions->links() }}</section>
</div>
@endsection
