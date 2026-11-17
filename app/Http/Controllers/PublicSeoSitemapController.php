<?php

namespace App\Http\Controllers;

use App\Services\PublicSeo;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class PublicSeoSitemapController extends Controller
{
    public function __invoke(Request $request, PublicSeo $seo): Response
    {
        $urls=$seo->sitemapUrls($request);
        abort_if($urls === null,404);
        $xml='<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml.='<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $url) {
            $xml.='  <url><loc>'.htmlspecialchars($url,ENT_XML1|ENT_QUOTES,'UTF-8').'</loc></url>'."\n";
        }
        $xml.='</urlset>'."\n";
        return response($xml, 200, ['Content-Type'=>'application/xml; charset=UTF-8','Cache-Control'=>'no-store']);
    }
}
