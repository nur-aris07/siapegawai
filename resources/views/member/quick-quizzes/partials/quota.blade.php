<section class="qq-quota-summary" aria-label="Kuota Quiz Kilat hari ini">
    <div><span class="workspace-label">PAKET AKTIF</span><strong>{{ $quotaToday['plan_name'] ?? 'Belum tersedia' }}</strong></div>
    <div><span class="workspace-label">KUOTA HARI INI · {{ $quotaToday['bucket_date_wib'] }} WIB</span>
        <strong>@if(!$quotaToday['allowed']) Belum dikonfigurasi @elseif($quotaToday['unlimited']) Tanpa batas @else {{ $quotaToday['daily_limit'] }} percobaan @endif</strong></div>
    <div><span class="workspace-label">DIGUNAKAN</span><strong>{{ $quotaToday['consumed'] }} percobaan</strong></div>
    <div><span class="workspace-label">SISA HARI INI</span><strong>@if(!$quotaToday['allowed']) — @elseif($quotaToday['unlimited']) Tanpa batas @else {{ $quotaToday['remaining'] }} percobaan @endif</strong></div>
</section>
