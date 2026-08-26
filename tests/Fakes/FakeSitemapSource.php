<?php

namespace Splicewire\Beam\Sitemap\Tests\Fakes;

use Spatie\Sitemap\Tags\Url;
use Splicewire\Beam\Sitemap\Contracts\SitemapSource;

/**
 * A NAMED source, which is the point of it: the estate's own ad-hoc sources are anonymous classes, and
 * an anonymous class cannot exercise the key derivation at all (it has no name to derive from). This
 * one is what proves `beam.sitemap.sources.fake-sitemap-source` is minted from the class.
 */
class FakeSitemapSource implements SitemapSource
{
    public function urls(): iterable
    {
        yield Url::create('https://example.test/fake');
    }
}
