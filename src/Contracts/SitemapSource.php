<?php

namespace Splicewire\Beam\Sitemap\Contracts;

use Spatie\Sitemap\Contracts\Sitemapable;
use Spatie\Sitemap\Tags\Url;

/**
 * One contributor of URLs to the beam sitemap. Consumers (the beam-ux
 * EntrySitemapSource, a satellite's own vertical sources) register a source on
 * the {@see \Splicewire\Beam\Sitemap\SitemapSourceRegistry}; the arm owns the
 * contract and wiring, never the data.
 *
 * Yield only **real, public, canonical** resources — never gated/entitlement
 * content or an infinite parametric space (every possible number/name). Absolute
 * canonical URLs come from the {@see \Splicewire\Beam\Sitemap\Resolvers\SitemapBaseUrlResolver}
 * (default `config('app.url')`), consistent with the interlink convention.
 *
 * Relocated down from `laravel-satellite` (ADR-0166): the seam has zero satellite
 * coupling, so it homes at the tier of the content it enumerates.
 */
interface SitemapSource
{
    /**
     * @return iterable<Url|Sitemapable>
     */
    public function urls(): iterable;
}
