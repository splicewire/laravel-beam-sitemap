<?php

namespace Splicewire\Beam\Sitemap\Http;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Response;
use Spatie\Sitemap\Sitemap;
use Splicewire\Beam\Sitemap\SitemapSourceRegistry;

/**
 * Renders /sitemap.xml from every registered source. A host loads this on a live
 * route (over a static public/ file) so a site-mode gate can short-circuit it
 * before this runs. The rendered XML is cached (config TTL) so a crawler hit
 * doesn't rebuild it from the DB each time.
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

        $render = function () use ($registry): string {
            $sitemap = Sitemap::create();

            foreach ($registry->urls() as $url) {
                $sitemap->add($url);
            }

            return $sitemap->render();
        };

        $xml = $ttl > 0
            ? $cache->remember('beam.sitemap', $ttl, $render)
            : $render();

        return response($xml, 200, ['Content-Type' => 'text/xml; charset=UTF-8']);
    }
}
