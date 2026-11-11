@extends('layouts.admin')
@section('title', 'Kuota Quiz Kilat per Paket')
@section('content')
<div class="qq-quota-page">
    <header class="admin-v1-heading qq-quota-heading">
        <div>
            <p class="admin-v1-crumb"><a href="{{ route('admin.quick-quizzes.index') }}">Quiz Kilat</a> / Manfaat paket</p>
            <h1>Kuota Quiz Kilat</h1>
            <p class="muted">Kuota mengikuti paket peserta: Pejuang (gratis), Abdi Negara, atau VIP. Tidak ada pemberian akses Quiz Kilat per peserta.</p>
        </div>
    </header>

    <section class="admin-v1-panel qq-quota-panel" aria-labelledby="qq-policy-title">
        <h2 id="qq-policy-title">Manfaat harian per paket</h2>
        <p class="muted">Ketiga paket berikut sudah ditentukan sebagai identitas produk; <strong>angka kuotanya belum diisi otomatis</strong>. Setiap nilai harus disahkan Superadmin. Reset setiap pukul 00.00 WIB, dipakai bersama seluruh judul.</p>
        @if($errors->any())
            <div role="alert" class="admin-v1-muted">Periksa kembali isian kuota: {{ $errors->first() }}</div>
        @endif
        <div class="qq-plan-grid">
            @foreach($plans as $plan)
                @php($policy = $plan->quizQuotaPolicy)
                <article class="qq-plan-card">
                    <div class="qq-plan-head">
                        <div>
                            <h3>{{ $plan->name }}</h3>
                            <p class="muted">{{ $plan->is_paid ? 'Paket berbayar' : 'Paket gratis · dasar semua peserta aktif' }}</p>
                        </div>
                        <span class="qq-plan-status">{{ $policy?->status === 'active' ? 'Aktif' : ($policy?->status === 'draft' ? 'Draft' : 'Belum aktif') }}</span>
                    </div>
                    <p><strong>Kuota:</strong>
                        {{ !$policy || $policy->daily_limit === null && !$policy->unlimited ? 'Belum ditetapkan' : ($policy->unlimited ? 'Unlimited' : $policy->daily_limit.' attempt/hari') }}
                    </p>
                    <details class="qq-quota-details">
                        <summary>{{ $policy ? 'Ubah kuota paket' : 'Atur kuota paket' }}</summary>
                        <form method="POST" action="{{ route('admin.qq-quota.update', $plan) }}" class="qq-quota-form qq-plan-form">
                            @csrf @method('PUT')
                            <label>Kuota harian (kosongkan jika unlimited)
                                <input name="daily_limit" type="number" min="0" max="1000000" value="{{ $policy?->daily_limit }}">
                            </label>
                            <label>Status
                                <select name="status" required>
                                    <option value="draft" @selected(($policy?->status ?? 'draft') === 'draft')>Draft</option>
                                    <option value="active" @selected($policy?->status === 'active')>Aktif setelah disahkan</option>
                                    <option value="disabled" @selected($policy?->status === 'disabled')>Nonaktif</option>
                                </select>
                            </label>
                            <label class="qq-quota-check"><input type="checkbox" name="unlimited" value="1" @checked($policy?->unlimited)>Unlimited (harus disahkan secara eksplisit)</label>
                            <label>Berlaku mulai (WIB, opsional)
                                <input type="datetime-local" name="starts_at" value="{{ $policy?->starts_at?->timezone('Asia/Jakarta')->format('Y-m-d\TH:i') }}">
                            </label>
                            <label>Berakhir (WIB, opsional)
                                <input type="datetime-local" name="ends_at" value="{{ $policy?->ends_at?->timezone('Asia/Jakarta')->format('Y-m-d\TH:i') }}">
                            </label>
                            <label class="qq-quota-check qq-plan-confirm"><input type="checkbox" name="confirmed" value="1" required>Saya menyetujui angka/manfaat paket ini.</label>
                            <button class="button button-primary" type="submit">Simpan kuota {{ $plan->name }}</button>
                        </form>
                    </details>
                </article>
            @endforeach
        </div>
    </section>

    <section class="admin-v1-panel qq-quota-panel" aria-labelledby="qq-ownership-title">
        <h2 id="qq-ownership-title">Bagaimana peserta mendapat kuota?</h2>
        <p>Peserta aktif tanpa paket berbayar menggunakan <strong>Pejuang</strong>. Keanggotaan <strong>Abdi Negara</strong> atau <strong>VIP</strong> dibaca dari paket akun peserta yang masih berlaku, bukan grant Quiz Kilat. Perubahan paket dikelola pada <a href="{{ route('admin.participants.index') }}">Direktori peserta</a> dan dicatat dalam audit.</p>
        <p class="muted">Pembukaan daftar Quiz Kilat tidak mengurangi kuota. Setelah runtime peserta dibuat, halaman peserta akan menampilkan nama paket, kuota, pemakaian, dan sisa hari ini. Riwayat kuota lama dan CAT tidak diubah.</p>
    </section>
</div>
@endsection
