<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PublicSeo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class SiapegawaiPublicSeoTest extends TestCase
{
    use RefreshDatabase;

    private function approvedSeo(): void
    {
        config()->set('seo.canonical_origin', 'https://siapegawai.id');
        config()->set('seo.indexing_enabled', true);
        config()->set('seo.approved_indexable_routes', ['home','questions.index','practice.intro','page.kelas','page.paket']);
    }

    private function seoRequest(string $url, ?string $host = null, ?string $forwarded = null): Request
    {
        $server=['HTTPS'=>str_starts_with($url, 'https://') ? 'on' : 'off', 'HTTP_HOST'=>$host ?? 'siapegawai.id'];
        if ($forwarded !== null) $server['HTTP_X_FORWARDED_HOST']=$forwarded;
        $r=Request::create($url,'GET',[],[],[], $server);
        $route=Route::getRoutes()->match($r);
        $r->setRouteResolver(fn () => $route);
        return $r;
    }

    public function test_default_off_public_pages_and_legal_are_noindex_without_fake_canonical(): void
    {
        foreach (['/','/bank-soal','/latihan-gratis','/kelas','/paket','/tentang','/kontak','/privasi','/syarat-layanan'] as $url) {
            $response=$this->get($url)->assertOk()->assertHeader('X-Robots-Tag','noindex, follow')
                ->assertSee('<meta name="robots" content="noindex, follow">', false)
                ->assertDontSee('rel="canonical"',false);
            $this->assertSame(1, substr_count($response->getContent(), '<meta name="description"'));
            $this->assertSame(1, substr_count($response->getContent(), '<title>'));
            $this->assertStringContainsString('<html lang="id">',$response->getContent());
        }
        $this->get('/sitemap.xml')->assertNotFound();
    }

    public function test_public_pages_expose_initial_html_and_crawlable_anchor_links(): void
    {
        $paths=['/bank-soal','/latihan-gratis','/kelas','/paket'];
        $home=$this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('<main id="main"', $home);
        foreach ($paths as $path) {
            $this->assertStringContainsString('href="'.url($path).'"',$home);
            $body=$this->get($path)->assertOk()->getContent();
            $this->assertStringContainsString('<h1', $body);
            $this->assertStringContainsString('<main id="main"', $body);
            $this->assertStringContainsString('href="'.route('home').'"',$body);
        }
        $this->assertStringContainsString('User-agent: *',file_get_contents(public_path('robots.txt')));
        $this->assertStringNotContainsString('Disallow: /',file_get_contents(public_path('robots.txt')));
    }

    public function test_descriptions_are_unique_and_relevant_without_account_or_draft_data(): void
    {
        $seo=app(PublicSeo::class);
        $titles=[];$descriptions=[];
        foreach (['/','/bank-soal','/latihan-gratis','/kelas','/paket'] as $path) {
            $data=$seo->pageData($this->seoRequest('https://siapegawai.id'.$path));
            $titles[]=$data['title']; $descriptions[]=$data['description'];
            $this->assertStringNotContainsString('user_id', $data['description']);
            $this->assertStringNotContainsString('lunas', $data['description']);
        }
        $this->assertCount(5,array_unique($titles));
        $this->assertCount(5,array_unique($descriptions));
    }

    public function test_production_gate_needs_owner_route_approval_and_trusted_host(): void
    {
        $this->approvedSeo();
        $seo=app(PublicSeo::class);
        $request=$this->seoRequest('https://siapegawai.id/bank-soal');
        $this->assertFalse($seo->pageData($request)['indexable']); // current test env
        $this->app->detectEnvironment(fn () => 'production');
        try {
            $this->assertTrue($seo->pageData($request)['indexable']);
            $this->assertFalse($seo->pageData($this->seoRequest('https://evil.invalid/bank-soal','evil.invalid'))['indexable']);
            $this->assertFalse($seo->pageData($this->seoRequest('https://siapegawai.id/bank-soal','siapegawai.id','spoof.invalid'))['indexable']);
            $this->assertFalse($seo->pageData($this->seoRequest('http://siapegawai.id/bank-soal','siapegawai.id'))['indexable']);
            config()->set('seo.approved_indexable_routes', []);
            $this->assertFalse($seo->pageData($request)['indexable']);
            $this->assertNull($seo->sitemapUrls($request));
        } finally { $this->app->detectEnvironment(fn () => 'testing'); }
    }

    public function test_category_tracking_and_search_queries_have_distinct_canonical_policy(): void
    {
        $this->approvedSeo();
        $seo=app(PublicSeo::class);
        foreach (['/bank-soal','/bank-soal?utm_source=example','/bank-soal?kategori=TWK','/bank-soal?cari=','/bank-soal?gclid=ab12'] as $url) {
            $this->assertSame('https://siapegawai.id/bank-soal',$seo->canonical($this->seoRequest('https://siapegawai.id'.$url)));
        }
        foreach (['/bank-soal?cari=soal','/bank-soal?kategori=TWK&cari=x','/bank-soal?kategori=invalid','/bank-soal?session=secret','/bank-soal?cari=%3Cscript%3E','/bank-soal?sort=latest'] as $url) {
            $this->assertNull($seo->canonical($this->seoRequest('https://siapegawai.id'.$url)),$url);
        }
        $this->get('/bank-soal?cari=soal')->assertOk()->assertHeader('X-Robots-Tag','noindex, follow');
    }

    public function test_origin_validation_rejects_local_and_untrusted_values(): void
    {
        $seo=app(PublicSeo::class);
        foreach (['','http://siapegawai.id','https://localhost','https://127.0.0.1','https://example.com','https://www.example.com','https://example.net','https://example.org','https://siapegawai.id:443','https://siapegawai.id/path','https://user:pass@siapegawai.id','https://siapegawai.id?x=1'] as $origin) {
            config()->set('seo.canonical_origin',$origin);
            $this->assertNull($seo->origin(),$origin);
        }
        config()->set('seo.canonical_origin','https://siapegawai.id');
        $this->assertSame('https://siapegawai.id',$seo->origin());
    }

    public function test_sitemap_contains_only_owner_approved_canonical_urls_when_all_gates_hold(): void
    {
        $this->approvedSeo();
        $seo=app(PublicSeo::class);
        $request=$this->seoRequest('https://siapegawai.id/sitemap.xml');
        $this->assertNull($seo->sitemapUrls($request));
        $this->app->detectEnvironment(fn () => 'production');
        try {
            $urls=$seo->sitemapUrls($request);
            $this->assertCount(5,$urls);
            $this->assertSame('https://siapegawai.id/',$urls[0]);
            foreach ($urls as $url) {
                $this->assertStringStartsWith('https://siapegawai.id/',$url);
                $this->assertStringNotContainsString('?',$url);
            }
            $this->assertNotContains('https://siapegawai.id/privasi',$urls);
            config()->set('seo.approved_indexable_routes',['page.privasi','home']);
            $this->assertSame(['https://siapegawai.id/'],$seo->sitemapUrls($request));
        } finally { $this->app->detectEnvironment(fn () => 'testing'); }
    }

    public function test_non_public_and_private_routes_are_never_indexable(): void
    {
        $this->approvedSeo();
        $seo=app(PublicSeo::class);
        $this->app->detectEnvironment(fn () => 'production');
        try {
            foreach (['/privasi','/syarat-layanan','/latihan-gratis/kerjakan','/login','/admin','/dashboard'] as $path) {
                $this->assertFalse($seo->pageData($this->seoRequest('https://siapegawai.id'.$path))['indexable']);
            }
        } finally { $this->app->detectEnvironment(fn () => 'testing'); }
        $this->get('/login')->assertOk()->assertHeader('X-Robots-Tag','noindex, follow');
        // An unauthenticated redirect is not a public HTML indexing target.
        // Laravel can produce this response in its authentication exception handler,
        // outside the route's security-header middleware. Check access control and
        // the destination HTML's noindex directive instead of requiring a 302 header.
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/login')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, follow');
    }

    public function test_sitemap_is_xml_and_not_permitted_by_config_alone_without_production_gate(): void
    {
        $this->approvedSeo();
        $this->get('/sitemap.xml')->assertNotFound();
        $this->assertTrue(Route::has('seo.sitemap'));
        $this->assertStringContainsString('application/xml',file_get_contents(app_path('Http/Controllers/PublicSeoSitemapController.php')));
    }
}
