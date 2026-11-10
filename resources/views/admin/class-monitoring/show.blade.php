@extends('layouts.admin')
@section('title','Detail monitoring peserta')
@section('content')
<div class="admin-monitoring">
<div class="admin-v1-heading"><div>
    <p class="admin-v1-crumb"><a href="{{ route('admin.classes.index') }}">Kelas</a> / <a href="{{ route('admin.classes.monitoring.index',$class) }}">Monitoring</a> / Peserta</p>
    <h1>{{ $participant->name }}</h1>
    <p>Monitoring peserta di kelas {{ $class->title }}. Riwayat dipertahankan walaupun akses kelas dicabut.</p>
</div></div>
<section class="admin-v1-panel">
    <h2>Identitas dan hak akses</h2>
    <p><strong>Email:</strong> {{ $participant->email }}</p>
    <p><strong>Status akun:</strong> {{ $participant->status }}</p>
    <p><strong>Status enrollment:</strong> {{ $enrollment->status === 'active' ? 'Aktif' : 'Dicabut' }}</p>
    <p><strong>Terakhir diberikan akses:</strong> {{ $enrollment->approved_at?->format('d M Y H:i') ?? '—' }}</p>
    <p><strong>Progres materi:</strong> {{ $completedMaterials }} dari {{ $publishedMaterials }} ({{ $percent }}%)</p>
    @if($enrollment->status === 'revoked')
        <p class="admin-v1-muted">Akses peserta saat ini dicabut. Halaman ini hanya dapat dibaca admin berwenang; peserta tidak dapat membuka materi atau hasil privat selama akses dicabut.</p>
    @endif
</section>
<section class="admin-v1-panel">
    <h2>Materi kelas yang terbit</h2>
    <p class="admin-v1-muted">Materi draft tidak termasuk perhitungan. Penyelesaian materi lama tetap disimpan dan ditampilkan selama materi terkait masih published.</p>
    <div class="table-scroll"><table class="data-table admin-v1-table"><thead><tr><th>Materi</th><th>Progres</th><th>Diselesaikan pada</th></tr></thead><tbody>
        @forelse($materials as $material)
            <tr><td>{{ $material->title }}</td><td>{{ $material->completed_at === null ? 'Belum selesai' : 'Selesai' }}</td><td>{{ $material->completed_at ?? '—' }}</td></tr>
        @empty
            <tr><td colspan="3">Belum ada materi terbit di kelas ini.</td></tr>
        @endforelse
    </tbody></table></div>
    {{ $materials->links() }}
</section>
<section class="admin-v1-panel">
    <h2>Riwayat hasil Latihan Kelas</h2>
    <p class="admin-v1-muted">Menampilkan attempt yang sudah dikumpulkan atau habis waktu. Tidak mencakup hasil Tryout CAT maupun attempt yang masih berjalan.</p>
    <div class="table-scroll"><table class="data-table admin-v1-table"><thead><tr><th>Latihan</th><th>Percobaan</th><th>Status</th><th>Jawaban benar</th><th>Skor internal</th><th>Selesai pada</th></tr></thead><tbody>
        @forelse($attempts as $attempt)
            <tr><td>{{ $attempt->practice?->title ?? 'Latihan tidak tersedia' }}</td>
                <td>{{ $attempt->attempt_number }}</td>
                <td>{{ $attempt->status === 'completed' ? 'Dikumpulkan' : 'Habis waktu' }}</td>
                <td>{{ $attempt->correct_count }}/{{ $attempt->total_questions }}</td>
                <td>{{ number_format((float)$attempt->score_percent, 2, ',', '.') }}%</td>
                <td>{{ $attempt->finished_at?->format('d M Y H:i') ?? '—' }}</td></tr>
        @empty
            <tr><td colspan="6">Belum ada hasil Latihan Kelas yang selesai.</td></tr>
        @endforelse
    </tbody></table></div>
    {{ $attempts->links() }}
    <p class="admin-v1-muted">Nilai Latihan Kelas bukan skor resmi CPNS. Halaman ini tidak dapat mengubah nilai atau progres peserta.</p>
</section>
</div>
@endsection
