<?php

namespace App\Services;

class WhatsAppLinkService
{
    public function officialNumber(): ?string
    {
        $raw = preg_replace('/\D+/', '', (string) config('siapegawai.whatsapp_number'));
        if (str_starts_with($raw, '0')) $raw = '62'.substr($raw, 1);
        return preg_match('/^\d{9,15}$/', $raw) ? $raw : null;
    }

    public function registrationUrl(string $reference): ?string
    {
        if (! $number = $this->officialNumber()) return null;
        $message = 'Halo admin Siapegawai, saya sudah mendaftar. Kode referensi: '.$reference.'. Mohon informasi proses aktivasi.';
        return 'https://wa.me/'.$number.'?'.http_build_query(['text' => $message], '', '&', PHP_QUERY_RFC3986);
    }
}
