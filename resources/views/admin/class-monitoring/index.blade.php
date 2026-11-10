@extends('layouts.admin')
@section('title','Monitoring kelas')
@section('content')
<div class="admin-monitoring" data-monitoring-published="{{ $totals['publishedMaterials'] }}">
<div class="admin-v1-heading"><div>
    <p class="admin-v1-crumb"><a href="{{ route('admin.classes.index') }}">Kelas</a> / <a href="{{ route('admin.classes.edit',$class) }}">Detail</a> / Monitoring</p>
    <h1>Monitoring: {{ $class->title }}</h1>
    <p>Ringkasan progres materi terbit dan hasil Latihan Kelas. Data ini hanya untuk dibaca.</p>
</div></div>
<section class="admin-v1-panel" aria-labelledby="monitor-summary-title">
    <h2 id="monitor-summary-title">Ringkasan kelas</h2>
    <dl class="admin-monitoring-summary">
        <div><dt>Enrollment aktif</dt><dd>{{ $totals['active'] }}</dd></div>
        <div><dt>Akses dicabut</dt><dd>{{ $totals['revoked'] }}</dd></div>
        <div><dt>Materi terbit</dt><dd>{{ $totals['publishedMaterials'] }}</dd></div>
        <div><dt>Peserta aktif dengan progres</dt><dd>{{ $totals['progressed'] }}</dd></div>
        <div><dt>Rata-rata progres aktif</dt><dd>{{ $totals['averagePercent'] }}%</dd></div>
        <div><dt>Latihan terbit</dt><dd>{{ $totals['publishedPractices'] }}</dd></div>
        <div><dt>Percobaan selesai</dt><dd>{{ $totals['finishedAttempts'] }}</dd></div>
    </dl>
    <p class="admin-v1-muted">Rata-rata memakai peserta dengan enrollment aktif dan materi yang saat ini diterbitkan. Percobaan selesai mencakup peserta yang aksesnya sudah dicabut.</p>
</section>
<section class="admin-v1-panel" aria-labelledby="monitor-members-title">
    <div class="admin-v1-panel-top"><div>
        <h2 id="monitor-members-title">Peserta terdaftar di kelas</h2>
        <p>Filter dan pencarian diproses oleh server tanpa memuat ulang halaman jika JavaScript tersedia.</p>
    </div></div>
    <form method="GET" action="{{ route('admin.classes.monitoring.index',$class) }}" class="admin-v1-filters admin-monitoring-filters" data-monitoring-table data-url="{{ route('admin.classes.monitoring.index',$class) }}">
        <label class="admin-v1-field" for="monitoring-search">Nama atau email peserta
            <input id="monitoring-search" type="search" name="q" maxlength="100" autocomplete="off" value="{{ $filters['q'] ?? '' }}" placeholder="Cari peserta">
        </label>
        <label class="admin-v1-field" for="monitoring-status">Status enrollment
            <select id="monitoring-status" name="status">
                @foreach(['all'=>'Semua status','active'=>'Aktif','revoked'=>'Dicabut'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? 'all') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <button type="submit" class="button button-outline button-compact">Terapkan</button>
        <a class="button button-outline button-compact" id="monitoring-reset" href="{{ route('admin.classes.monitoring.index',$class) }}">Reset</a>
    </form>
    <p class="admin-monitoring-scroll-hint">Tabel dapat digeser ke samping untuk melihat semua kolom.</p>
    <div class="table-scroll admin-monitoring-table-wrap" role="region" tabindex="0" aria-label="Tabel monitoring peserta, dapat digeser secara horizontal">
        <table class="data-table admin-v1-table" aria-describedby="monitoring-info"><thead><tr>
            <th scope="col">Peserta</th><th scope="col">Status akses</th><th scope="col">Status akun</th><th scope="col">Progres materi</th>
            <th scope="col">Latihan selesai</th><th scope="col">Skor terakhir</th><th scope="col">Skor terbaik</th><th scope="col">Detail</th>
        </tr></thead><tbody id="monitoring-rows">
            @forelse($participants as $participant)
                <tr>
                    <td><strong>{{ $participant->name }}</strong><p class="admin-v1-muted">{{ $participant->email }}</p></td>
                    <td>{{ $participant->enrollment_status === 'active' ? 'Aktif' : 'Dicabut' }}</td>
                    <td>{{ $participant->account_status }}</td>
                    <td>{{ $participant->completed_count }}/{{ $totals['publishedMaterials'] }} ({{ $participant->progress_percent }}%)</td>
                    <td>{{ $participant->finished_attempts }}</td>
                    <td>{{ $participant->latest_score === null ? '—' : number_format((float)$participant->latest_score,2,',','.') . '%' }}</td>
                    <td>{{ $participant->best_score === null ? '—' : number_format((float)$participant->best_score,2,',','.') . '%' }}</td>
                    <td><a href="{{ route('admin.classes.monitoring.show',[$class,$participant->user_id]) }}">Lihat</a></td>
                </tr>
            @empty
                <tr><td colspan="8">Tidak ada peserta yang sesuai filter.</td></tr>
            @endforelse
        </tbody></table>
    </div>
    <div class="admin-v1-table-foot" id="monitoring-ajax-foot" hidden>
        <span id="monitoring-info" role="status" aria-live="polite"></span>
        <div><button type="button" id="monitoring-prev" class="button button-outline button-compact">Sebelumnya</button>
        <button type="button" id="monitoring-next" class="button button-outline button-compact">Berikutnya</button></div>
    </div>
    <div id="monitoring-plain-pagination">{{ $participants->links() }}</div>
    <p class="admin-v1-muted">Skor Latihan Kelas adalah nilai internal, bukan nilai resmi CPNS; tidak digabung dengan CAT/Tryout.</p>
</section>
</div>
@vite('resources/js/admin-class-monitoring.js')
@endsection
