<?php

namespace App\Http\Controllers;

use App\Models\Community;
use App\Support\Seo;
use Illuminate\Http\Response;

/**
 * robots.txt and sitemap.xml, following the SEO settings.
 */
class SeoController extends Controller
{
    public function robots(): Response
    {
        $lines = Seo::siteIndexed()
            ? ['User-agent: *', 'Disallow: /admin', 'Disallow: /settings', 'Disallow: /chat', 'Disallow: /notifications', 'Disallow: /*/checkout', '', 'Sitemap: '.route('sitemap')]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain']);
    }

    public function sitemap(): Response
    {
        $urls = [];

        if (Seo::siteIndexed()) {
            $urls[] = ['loc' => route('home'), 'lastmod' => null];

            Community::query()
                ->where('privacy', Community::PRIVACY_PUBLIC)
                ->where('seo_index', true)
                ->orderBy('id')
                ->get()
                ->each(function (Community $community) use (&$urls) {
                    $urls[] = ['loc' => route('communities.about', $community), 'lastmod' => $community->updated_at];
                    $urls[] = ['loc' => route('communities.feed', $community), 'lastmod' => null];

                    if (Seo::postsIndexed($community)) {
                        $community->posts()->latest()->limit(1000)->get(['id', 'community_id', 'updated_at'])
                            ->each(function ($post) use (&$urls, $community) {
                                $urls[] = ['loc' => route('posts.show', [$community, $post]), 'lastmod' => $post->updated_at];
                            });
                    }
                });
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach (array_slice($urls, 0, 50000) as $url) {
            $xml .= '  <url><loc>'.e($url['loc']).'</loc>'.($url['lastmod'] ? '<lastmod>'.$url['lastmod']->toAtomString().'</lastmod>' : '')."</url>\n";
        }

        return response($xml.'</urlset>'."\n", 200, ['Content-Type' => 'application/xml']);
    }
}
