<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $kind, public string $displayName = 'Peserta') {}

    public static function contentFor(string $kind): array
    {
        return match ($kind) {
            'free_registered' => ['Akun gratis Siapegawai aktif', 'Pendaftaran berhasil. Akun GRATIS kamu sudah aktif dengan paket dasar Pejuang.'],
            'google_registered' => ['Akun gratis Google kamu aktif', 'Pendaftaran melalui Google berhasil. Akun GRATIS kamu aktif dengan paket dasar Pejuang.'],
            'invitation_activated' => ['Akun undangan Siapegawai aktif', 'Pengaturan password dan persetujuan kebijakan selesai. Akun peserta kini aktif dengan paket dasar Pejuang.'],
            'legacy_approved' => ['Aktivasi akun Siapegawai disetujui', 'Pendaftaran historis kamu sudah disetujui. Kamu bisa masuk ke Siapegawai.'],
            'legacy_rejected' => ['Informasi status pendaftaran Siapegawai', 'Pendaftaran historis kamu belum dapat disetujui. Hubungi pengelola melalui kanal resmi bila memerlukan bantuan.'],
            'smtp_test' => ['[UAT] Tes SMTP Siapegawai', 'Ini pesan pengujian SMTP yang diminta superadmin. Tidak ada perubahan pada akun atau paket.'],
            default => throw new \InvalidArgumentException('Unknown account email event'),
        };
    }

    public function envelope(): Envelope { return new Envelope(subject: self::contentFor($this->kind)[0]); }
    public function content(): Content
    {
        return new Content(view: 'emails.account-status', text: 'emails.account-status-text', with: [
            'headline'=>self::contentFor($this->kind)[0],
            'messageText'=>self::contentFor($this->kind)[1],
            'displayName'=>$this->displayName,
            'homeUrl'=>rtrim((string) config('app.url'), '/').'/login',
        ]);
    }
}
