<?php

namespace App\Services;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Safe public-only metadata and an explicit, fail-closed indexing policy.
 * No request host or user/session values are ever used to create canonical URLs.
 */
final class PublicSeo
{
    private const PAGES = [
        'home' => ['/', 'Siapegawai — Belajar dan latihan CPNS', 'Kenali Siapegawai, layanan belajar CPNS independen dengan latihan gratis, contoh soal, informasi kelas, dan pilihan paket.'],
        'questions.index' => ['/bank-soal', 'Bank Soal dan Pembahasan CPNS', 'Jelajahi contoh soal TWK, TIU, TKP, dan SKB beserta pembahasannya melalui bank soal publik Siapegawai.'],
        'practice.intro' => ['/latihan-gratis', 'Latihan SKD Gratis — 9 Soal', 'Coba latihan SKD singkat berisi sembilan soal TWK, TIU, dan TKP. Lihat pembahasan setelah latihan selesai.'],
        'page.kelas' => ['/kelas', 'Informasi dan Rencana Kelas CPNS', 'Lihat informasi rencana kelas SKD dan SKB di Siapegawai. Ketersediaan dan status program ditampilkan sesuai kondisi sebenarnya.'],
        'page.paket' => ['/paket', 'Pilihan Paket Belajar Siapegawai', 'Bandingkan informasi paket belajar yang sudah diterbitkan. Pengajuan perubahan paket diproses manual, tanpa checkout otomatis.'],
        'page.tentang' => ['/tentang', 'Tentang Siapegawai', 'Pelajari tujuan dan informasi mengenai Siapegawai sebagai layanan belajar CPNS independen.'],
        'page.kontak' => ['/kontak', 'Kontak dan Bantuan Siapegawai', 'Temukan informasi kontak dan kanal bantuan Siapegawai yang tersedia pada website publik.'],
        'page.privasi' => ['/privasi', 'Informasi Privasi Siapegawai', 'Baca informasi privasi yang disediakan oleh Siapegawai. Dokumen ini perlu pemeriksaan legal sebelum publikasi akhir.'],
        'page.syarat-layanan' => ['/syarat-layanan', 'Informasi Syarat Layanan Siapegawai', 'Baca informasi syarat layanan Siapegawai. Dokumen ini masih memerlukan validasi legal dan bisnis sebelum final.'],
    ];
    private const ELIGIBLE = ['home', 'questions.index', 'practice.intro', 'page.kelas', 'page.paket', 'page.tentang', 'page.kontak'];

    public function origin(): ?string
    {
        $raw = config('seo.canonical_origin');
        if (!is_string($raw) || $raw === '' || trim($raw) !== $raw || strlen($raw) > 253) return null;
        if (!preg_match('~^https://([a-z0-9](?:[a-z0-9.-]*[a-z0-9])?)/?$~iD', $raw, $m)) return null;
        $host = strtolower($m[1]);
        if (strlen($host) > 253 || !str_contains($host, '.') || str_contains($host, '..') || preg_match('~(^|\.)(localhost|local|test|example|invalid)$~i', $host)) return null;
        // IANA-reserved documentation domains are never acceptable canonical origins.
        // Other HTTPS hosts still require an explicit, operator-reviewed deployment setting.
        if (preg_match('~(^|\.)example\.(com|net|org)$~i', $host)) return null;
        if (filter_var($host, FILTER_VALIDATE_IP)) return null;
        foreach (explode('.', $host) as $part) if ($part === '' || strlen($part)>63 || str_starts_with($part,'-') || str_ends_with($part,'-')) return null;
        return 'https://'.$host;
    }

    private function approved(): array
    {
        $routes = config('seo.approved_indexable_routes', []);
        if (!is_array($routes)) return [];
        return array_values(array_intersect(self::ELIGIBLE, array_filter($routes, 'is_string')));
    }

    public function gate(Request $request): bool
    {
        $origin=$this->origin();
        if (!$origin || !app()->environment('production') || config('seo.indexing_enabled') !== true || !$request->isSecure()) return false;
        $host=parse_url($origin, PHP_URL_HOST);
        // Request and forwarded hosts must not silently switch the origin. Reject
        // aliases/ports/comma-delimited proxy headers unless deployment reviewed.
        if (strtolower($request->getHost()) !== $host) return false;
        $rawHost=$request->headers->get('host', '');
        if (strtolower($rawHost) !== $host) return false;
        $forwarded=$request->headers->get('x-forwarded-host');
        if ($forwarded !== null && strtolower($forwarded) !== $host) return false;
        return true;
    }

    public function pageName(Request $request): ?string
    {
        $name=$request->route()?->getName();
        return is_string($name) && array_key_exists($name,self::PAGES) ? $name : null;
    }

    public function querySafe(Request $request, ?string $name): bool
    {
        if ($name === null) return false;
        $query=$request->query();
        if (!is_array($query)) return false;
        foreach ($query as $key=>$value) {
            if (!is_string($key) || !is_string($value) || strlen($value)>512) return false;
            $tracking=(bool)preg_match('/^utm_[a-z0-9_]+$/i',$key) || in_array(strtolower($key),['gclid','fbclid','msclkid'],true);
            if ($tracking) continue;
            if ($name === 'questions.index' && $key === 'kategori') {
                if (!in_array($value,['Semua','TWK','TIU','TKP','SKB'],true)) return false;
                continue;
            }
            if ($name === 'questions.index' && $key === 'cari') {
                if (trim($value) !== '') return false; // internal searches are NOINDEX
                continue;
            }
            return false; // unknown, token, session or unexpected parameters
        }
        return true;
    }

    public function canonical(Request $request): ?string
    {
        $name=$this->pageName($request);
        $origin=$this->origin();
        if (!$origin || !$name || !$this->querySafe($request,$name)) return null;
        return $origin.self::PAGES[$name][0];
    }

    public function pageData(Request $request): array
    {
        $name=$this->pageName($request);
        $info=self::PAGES[$name] ?? null;
        $title=$info[1] ?? 'Siapegawai — Layanan Belajar CPNS';
        $desc=$info[2] ?? 'Informasi dan layanan belajar CPNS independen dari Siapegawai.';
        $url=$this->canonical($request);
        $index=$name !== null && in_array($name,$this->approved(),true) && $this->gate($request) && $this->querySafe($request,$name);
        return ['title'=>$title, 'description'=>$desc, 'canonical'=>$url,
            'indexable'=>$index, 'robots'=>$index ? 'index, follow' : 'noindex, follow'];
    }

    public function indexable(Request $request, Response $response): bool
    {
        if ($response->getStatusCode() !== 200 || $request->method() !== 'GET') return false;
        $type=strtolower((string)$response->headers->get('Content-Type','text/html'));
        if (!str_contains($type,'text/html')) return false;
        return $this->pageData($request)['indexable'];
    }

    public function sitemapUrls(Request $request): ?array
    {
        if (!$this->gate($request)) return null;
        $origin=$this->origin();
        if ($origin===null) return null;
        $urls=[];
        foreach (self::ELIGIBLE as $name) {
            if (in_array($name,$this->approved(),true) && \Illuminate\Support\Facades\Route::has($name)) {
                $urls[]=$origin.self::PAGES[$name][0];
            }
        }
        return $urls ?: null;
    }
}
