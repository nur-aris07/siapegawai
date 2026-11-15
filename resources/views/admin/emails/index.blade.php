@extends('layouts.admin')
@section('title','Email & SMTP')
@section('content')
<div class="admin-v1-heading"><div><p class="admin-v1-crumb">Operasional / Notifikasi</p><h1>Email dan SMTP</h1><p>Pratinjau pesan, pemeriksaan konfigurasi tanpa menampilkan password SMTP, dan status antrean. Hanya superadmin.</p></div></div>
<div class="card" style="padding:24px;margin-bottom:18px"><h2>Diagnostik tanpa kredensial</h2>
<p>SMTP: <strong>{{ $smtpReady ? 'Siap secara konfigurasi' : 'Belum siap' }}</strong> · Penerima UAT: <strong>{{ $testRecipientReady ? 'Terkonfigurasi' : 'Belum diset' }}</strong> · Database outbox: <strong>{{ $tableReady ? 'Ada' : 'Perlu migration' }}</strong></p>
<p>Queue database: <strong>{{ $queueReady ? 'Dipilih' : 'Belum dipilih' }}</strong> · Pengiriman otomatis: <strong>{{ $sendEnabled ? 'ON' : 'OFF' }}</strong> · Siap otomatis: <strong>{{ $serviceReady ? 'Ya' : 'Belum' }}</strong></p>
<p>Worker email lokal: <strong>{{ $workerSeen ? 'Terdeteksi (heartbeat terbaru)' : 'Belum terdeteksi / berhenti' }}</strong> · Status ini bukan bukti pesan diterima Inbox.</p>
<p class="muted">Setelah aktivasi lokal C-03, biarkan worker berjalan. Jika komputer mati atau jendela worker ditutup, pengiriman otomatis akan tertahan di antrean sampai worker dijalankan lagi. Untuk server produksi gunakan process manager resmi.</p>
<p class="muted">Status konfigurasi bukan bukti SMTP dapat terkoneksi, worker berjalan, atau email diterima mailbox.</p>
<p><strong>Mengurangi risiko masuk Spam:</strong> gunakan alamat From yang diizinkan provider; untuk domain sendiri, konfigurasikan SPF, DKIM, dan DMARC pada DNS, cek penyelarasan identitas pengirim dan reputasi domain. Tidak ada opsi aplikasi yang menjamin masuk Inbox.</p>
<p class="muted">Di Gmail: buka pesan → menu lainnya → Tampilkan yang asli (Show original); periksa hasil SPF, DKIM, DMARC. Jangan membagikan password SMTP atau header lengkap yang berisi data pribadi.</p></div>
<div class="card" style="padding:24px;margin-bottom:18px"><h2>Pratinjau template</h2>
<p>Pratinjau tidak mengirim email.</p><div style="display:flex;flex-wrap:wrap;gap:10px">
@foreach (['free_registered'=>'Registrasi gratis','google_registered'=>'Registrasi Google','invitation_activated'=>'Aktivasi undangan','legacy_approved'=>'Disetujui','legacy_rejected'=>'Tidak disetujui','smtp_test'=>'Email uji'] as $type=>$label)
<a class="button button-outline" target="_blank" rel="noopener" href="{{ route('admin.account-emails.preview',$type) }}">{{ $label }}</a>
@endforeach
</div></div>
<div class="card" style="padding:24px;margin-bottom:18px"><h2>Kirim email uji SMTP</h2>
<p>Hanya dikirim ke alamat <code>SIAPEGAWAI_SMTP_TEST_RECIPIENT</code> pada .env; tidak bisa diubah melalui form.</p>
<form method="POST" action="{{ route('admin.account-emails.test') }}">@csrf<button type="submit" class="button button-primary" {{ $smtpReady && $testRecipientReady ? '' : 'disabled' }}>Kirim satu email uji</button></form>
<p class="muted">Jika SMTP menerima email, periksa Inbox, Spam, dan Promosi terlebih dahulu. Tunggu sebelum mencoba lagi untuk menghindari pesan ganda. Diterima SMTP tidak sama dengan berhasil masuk Inbox. Tombol ini tidak mengubah status peserta.</p></div>
<div class="card" style="padding:24px"><h2>Outbox notifikasi akun</h2>
@if (!$tableReady)<p>Migration outbox belum dijalankan di database aplikasi; fitur otomatis tidak tersedia.</p>
@else
<p>Pending: {{ $counts['pending'] ?? 0 }} · Processing: {{ $counts['processing'] ?? 0 }} · Diterima SMTP: {{ $counts['sent'] ?? 0 }} · Gagal: {{ $counts['failed'] ?? 0 }}</p>
<p class="muted">Status <strong>sent</strong> hanya berarti SMTP menerima pengiriman, bukan bukti masuk Inbox. Sebelum meminta kirim ulang, periksa Inbox, Spam, dan Promosi. Kirim ulang hanya oleh superadmin, minimal jeda 30 menit, maksimal dua kali per notifikasi asli.</p>
<div class="table-responsive"><table><thead><tr><th>ID</th><th>Event</th><th>Status</th><th>Percobaan</th><th>Kategori gagal</th><th>Tindakan</th></tr></thead><tbody>
@forelse($items as $item)<tr><td>{{ $item->id }}</td><td>{{ $item->event_type }}</td><td>{{ $item->status }}</td><td>{{ $item->attempts }}</td><td>{{ $item->failure_code ?: '—' }}</td><td>@if($item->status==='pending' && $item->attempts===0)
<form method="POST" action="{{ route('admin.account-emails.retry', $item) }}">@csrf<button class="button button-outline" type="submit">Antrekan</button></form>
@elseif($item->status==='sent' && str_starts_with((string) $item->event_key, 'account:'))
<form method="POST" action="{{ route('admin.account-emails.resend', $item) }}">
@csrf
<label style="display:block;font-size:12px;margin:0 0 8px"><input type="checkbox" name="checked_mailbox" value="1" required> Sudah periksa Inbox, Spam, dan Promosi</label>
<button class="button button-outline" type="submit">Minta kirim ulang</button>
</form>
@else — @endif</td></tr>
@empty<tr><td colspan="6">Belum ada notifikasi akun yang tercatat.</td></tr>@endforelse
</tbody></table></div>
@endif</div>
@endsection
