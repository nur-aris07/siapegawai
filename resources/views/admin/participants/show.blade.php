@extends('layouts.admin')
@section('title','Detail Peserta')
@section('content')
<div class="admin-v1-heading"><div><p class="admin-v1-crumb"><a href="{{ route('admin.participants.index') }}">Direktori peserta</a> / Detail akun</p><h1>Detail peserta</h1><p>Informasi akun dan pengamanan akses tanpa mengubah hasil tryout.</p></div></div>
<div class="admin-v1-catalog-grid">
 <section class="admin-v1-panel">
  <h2>Identitas dan akses</h2>
  <dl class="status-details">
   <dt>Nama</dt><dd>{{ $participant->name }}</dd>
   <dt>Email</dt><dd>{{ $participant->email }}</dd>
   <dt>Status akun</dt><dd>{{ ['active'=>'Aktif','pending'=>'Menunggu aktivasi','rejected'=>'Ditolak','suspended'=>'Ditangguhkan'][$participant->status] ?? $participant->status }}</dd>
   <dt>Terdaftar</dt><dd>{{ $participant->created_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</dd>
   <dt>Referensi registrasi</dt><dd>{{ $registration?->reference_code ?? 'Akun development / tidak ada pendaftaran web' }}</dd>
   <dt>Status registrasi</dt><dd>{{ $registration?->status ?? 'Tidak tercatat' }}</dd>
   <dt>Grant member</dt><dd>{{ $memberGrant?->status ?? 'Belum ada' }}</dd>
   <dt>Percobaan CAT</dt><dd>{{ $attemptCount }}</dd>
  </dl>
  @if($registration?->status === 'pending')
   <p class="admin-v1-muted">Aktivasi akun pending hanya melalui proses verifikasi pendaftaran.</p>
   <a class="admin-v1-inline-link" href="{{ route('registration.show', $participant) }}">Tinjau registrasi →</a>
  @endif
 </section>
 <section class="admin-v1-panel">
  <h2>Keamanan akun</h2>
  @if(auth()->user()->role !== 'superadmin')
   <p>Perubahan status hanya dapat dilakukan oleh Superadmin.</p>
  @elseif($participant->status === 'active' || $participant->status === 'suspended')
   <p>{{ $participant->status === 'active' ? 'Tangguhkan akses ke CAT dan dashboard privat. Riwayat tryout tetap disimpan.' : 'Aktifkan kembali akses yang sebelumnya ditangguhkan. Tidak membuat grant baru atau mengubah keputusan registrasi.' }}</p>
   <form method="POST" class="admin-v1-action-form" action="{{ route('admin.participants.status', $participant) }}">
    @csrf
    <input type="hidden" name="action" value="{{ $participant->status === 'active' ? 'suspend' : 'restore' }}">
    <label class="admin-v1-field" for="participant-reason">Alasan tindakan (wajib, minimal 12 karakter)<textarea id="participant-reason" name="reason" required minlength="12" maxlength="500" rows="4">{{ old('reason') }}</textarea></label>
    <label class="check-line"><input type="checkbox" name="confirmed" value="1" required><span>Saya telah memeriksa akun dan menyetujui pencatatan audit tindakan ini.</span></label>
    <button class="button {{ $participant->status === 'active' ? 'button-outline' : 'button-primary' }}" type="submit">{{ $participant->status === 'active' ? 'Tangguhkan peserta' : 'Aktifkan kembali' }}</button>
   </form>
  @else
   <p>Akun belum aktif atau ditolak. Gunakan alur verifikasi Registrasi; perubahan status langsung tidak tersedia di sini.</p>
  @endif
 </section>
</div>
<section class="admin-v1-panel admin-v1-catalog-topics">
 <h2>Audit pengamanan akun</h2>
 @forelse($accountAudits as $entry)
  <div class="admin-v1-topic-row"><strong>{{ $entry->action === 'participant.suspend' ? 'Penangguhan' : 'Aktivasi kembali' }}</strong>
   <p class="admin-v1-muted">{{ $entry->created_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB · {{ data_get($entry->after_summary, 'reason', 'Tanpa catatan') }}</p></div>
 @empty
  <p class="admin-v1-muted">Belum ada tindakan keamanan akun yang tercatat.</p>
 @endforelse
</section>
@endsection
