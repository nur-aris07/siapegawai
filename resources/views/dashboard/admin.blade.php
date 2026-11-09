@extends('layouts.admin')
@section('title','Ringkasan Operasional')
@section('content')
<div class="admin-v1-heading"><div><p class="admin-v1-crumb">Administrasi / Ringkasan</p><h1>Ringkasan operasional</h1><p>Prioritas pekerjaan dan konten berdasarkan data aplikasi.</p></div></div>
<div class="admin-v1-metrics admin-v1-dashboard-metrics" aria-label="Data operasional saat ini">
<div><strong>{{ $pendingCount }}</strong><span>Registrasi menunggu keputusan</span></div>
<div><strong>{{ $draftQuestions }}</strong><span>Soal draft</span></div>
<div><strong>{{ $publishedQuestions }}</strong><span>Soal diterbitkan</span></div>
</div>
<div class="admin-v1-dashboard-panels">
  <section class="admin-v1-panel"><div class="admin-v1-panel-top"><div><h2>Registrasi peserta</h2><p>Periksa referensi dan bukti konfirmasi manual sebelum mengaktifkan akun.</p></div></div><a class="admin-v1-inline-link" href="{{ route('registration.index') }}">Tinjau daftar registrasi <svg class="icon icon-small" aria-hidden="true"><use href="#ic-arrow-right"></use></svg></a></section>
  <section class="admin-v1-panel"><div class="admin-v1-panel-top"><div><h2>Bank soal produksi</h2><p>Susun soal, periksa pembahasan, lalu terbitkan versi yang telah direview.</p></div></div><a class="admin-v1-inline-link" href="{{ route('questions.admin.index') }}">Kelola bank soal <svg class="icon icon-small" aria-hidden="true"><use href="#ic-arrow-right"></use></svg></a></section>
</div>
<div class="admin-v1-dashboard-panels admin-v1-second-panels">
  <section class="admin-v1-panel"><h2>Kategori & topik</h2><p>Atur jalur materi TWK, TIU, TKP dan SKB.</p><a class="admin-v1-inline-link" href="{{ route('subjects.admin.index') }}">Kelola kategori materi</a></section>
  <section class="admin-v1-panel"><h2>Template tryout</h2><p>Pilih dan kunci versi soal untuk paket ujian. Peserta aktif dapat mengerjakan template yang diterbitkan; hasil tersimpan di MySQL.</p><a class="admin-v1-inline-link" href="{{ route('tryouts.admin.index') }}">Susun template tryout</a></section>
</div>
<p class="admin-v1-footnote">Kelas, paket berlangganan, pengaturan superadmin, dan MFA admin belum tersedia pada rilis ini. Jangan gunakan aplikasi sebagai layanan produksi sebelum kontrol keamanan rilis terpenuhi.</p>
@endsection
