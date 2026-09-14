<?php

namespace Splicewire\Beam\Sitemap\Http;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Response;
use Spatie\Sitemap\Sitemap;
use Splicewire\Beam\Sitemap\Contracts\LiveSitemapSource;
use Splicewire\Beam\Sitemap\SitemapSourceRegistry;

/**
 * Renders /sitemap.xml from every registered source. A host loads this on a live
 * route (over a static public/ file) so a site-mode gate can short-circuit it
 * before this runs. The rendered XML is cached (config TTL) so a crawler hit
 * doesn't rebuild it from the DB each time. A LiveSitemapSource opts the combined
 * response out of caching so a current visibility decision cannot be bypassed.
 *
 * Relocated down from `laravel-satellite` (ADR-0166); config keys moved to
 * `beam.sitemap.*`. The arm ships the controller but NOT the route (a host wires
 * the route into its own gated group — satellite still owns its Marquee-gated
 * routes/sitemap.php).
 */
class SitemapController
{
    public function __invoke(SitemapSourceRegistry $registry, Cache $cache): Response
    {
        $ttl = (int) config('beam.sitemap.cache_ttl', 3600);
        $sources = $registry->all();
        $live = false;
        foreach ($sources as $source) {
            if ($source instanceof LiveSitemapSource) {
                $live = true;
                break;
            }
        }

        $render = function () use ($sources): string {
            $sitemap = Sitemap::create();

            foreach ($sources as $source) {
                foreach ($source->urls() as $url) {
                    $sitemap->add($url);
                }
            }

            return $sitemap->render();
        };

        $xml = $ttl > 0 && ! $live
            ? $cache->remember('beam.sitemap', $ttl, $render)
            : $render();

        $headers = ['Content-Type' => 'text/xml; charset=UTF-8'];
        if ($live) {
            $headers['Cache-Control'] = 'no-store, private';
        }

        return response($xml, 200, $headers);
    }
}
