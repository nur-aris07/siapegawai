<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Only public, non-secret presentation data. Never an entitlement/feature-flag store. */
final class PublicSiteSettings
{
    private const CACHE_KEY = 'siapegawai:public-site-settings:v1';
    private const TTL = 300;

    public const FIELDS = [
        'site_name' => ['label' => 'Nama tampilan website', 'default' => 'Siapegawai', 'max' => 65, 'type' => 'text'],
        'tagline' => ['label' => 'Tagline singkat', 'default' => 'Ruang belajar persiapan CPNS independen', 'max' => 160, 'type' => 'text'],
        'support_email' => ['label' => 'Email bantuan publik', 'default' => '', 'max' => 254, 'type' => 'email'],
        'support_url' => ['label' => 'Tautan kanal bantuan resmi', 'default' => '', 'max' => 500, 'type' => 'url'],
        'footer_note' => ['label' => 'Catatan singkat footer', 'default' => 'Belajar dan berkembang bersama Siapegawai.', 'max' => 220, 'type' => 'text'],
        'social_instagram' => ['label' => 'Instagram resmi', 'default' => '', 'max' => 500, 'type' => 'instagram'],
        'social_youtube' => ['label' => 'YouTube resmi', 'default' => '', 'max' => 500, 'type' => 'youtube'],
        'social_tiktok' => ['label' => 'TikTok resmi', 'default' => '', 'max' => 500, 'type' => 'tiktok'],
    ];

    /** @return array<string, string> */
    public function defaults(): array
    {
        $values = [];
        foreach (self::FIELDS as $key => $meta) $values[$key] = $meta['default'];
        return $values;
    }

    /** Use bounded cache; invalid rows fall back to safe built-in defaults. */
    public function all(): array
    {
        try {
            if (! Schema::hasTable('site_settings')) return $this->defaults();
            return Cache::remember(self::CACHE_KEY, self::TTL, fn () => $this->normalize(
                DB::table('site_settings')->whereIn('key', array_keys(self::FIELDS))->pluck('value', 'key')->all()
            ));
        } catch (\Throwable) {
            // A missing database/cache during public rendering must not expose secrets or crash the layout.
            return $this->defaults();
        }
    }

    /** @param array<string, mixed> $raw */
    public function normalize(array $raw): array
    {
        $values = $this->defaults();
        foreach (self::FIELDS as $key => $meta) {
            if (! array_key_exists($key, $raw)) continue;
            $value = $raw[$key];
            if (! is_string($value)) continue;
            $value = trim($value);
            if ($value === '' && in_array($meta['type'], ['text'], true)) continue;
            if (! $this->valid($key, $value)) continue;
            $values[$key] = $value;
        }
        return $values;
    }

    public function valid(string $key, string $value): bool
    {
        $meta = self::FIELDS[$key] ?? null;
        if (! $meta || mb_strlen($value) > $meta['max'] || preg_match('/[<>\x00-\x1f\x7f]/u', $value)) return false;
        if ($value === '') return $meta['type'] !== 'text';
        if ($meta['type'] === 'email') return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
        if (in_array($meta['type'], ['url', 'instagram', 'youtube', 'tiktok'], true)) {
            $parts = parse_url($value);
            if (! is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https'
                || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
                || isset($parts['port']) || isset($parts['fragment'])) return false;
            $host = strtolower(rtrim($parts['host'], '.'));
            if (filter_var($host, FILTER_VALIDATE_IP) || $host === 'localhost'
                || ! str_contains($host, '.') || preg_match('/(?:^|\.)(?:local|internal|test|invalid)$/i', $host)
                || ! filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) return false;
            $allowed = match ($meta['type']) {
                'instagram' => ['instagram.com', 'www.instagram.com'],
                'youtube' => ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be'],
                'tiktok' => ['tiktok.com', 'www.tiktok.com'],
                default => null,
            };
            if ($allowed !== null && ! in_array($host, $allowed, true)) return false;
        }
        return true;
    }

    /** This token is computed from DB source, not cached values; stale saves are rejected. */
    public function version(array $values): string
    {
        ksort($values);
        return hash('sha256', json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
