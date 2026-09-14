<?php

namespace Splicewire\Beam\Sitemap\Tests;

use Spatie\Sitemap\Tags\Url;
use Splicewire\Beam\Sitemap\Contracts\LiveSitemapSource;
use Splicewire\Beam\Sitemap\Contracts\SitemapSource;
use Splicewire\Beam\Sitemap\Http\SitemapController;
use Splicewire\Beam\Sitemap\SitemapSourceRegistry;

class SitemapControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'beam.sitemap.cache_ttl' => 3600]);
        $this->app['router']->get('/sitemap.xml', SitemapController::class);
    }

    public function test_a_live_source_bypasses_primed_xml_and_rechecks_visibility_on_each_request(): void
    {
        $source = new class implements LiveSitemapSource
        {
            public bool $public = true;

            public int $reads = 0;

            public function urls(): iterable
            {
                $this->reads++;
                if ($this->public) {
                    yield Url::create('https://example.test/docs');
                }
            }
        };
        // Exercise lazy registration too: the marker belongs to the resolved source instance.
        $this->app->instance($source::class, $source);
        $this->app->make(SitemapSourceRegistry::class)->register($source::class);
        $cache = $this->app['cache']->store();
        $cache->put('beam.sitemap', '<urlset><url><loc>https://example.test/stale-private-guide</loc></url></urlset>', 3600);

        $this->get('/sitemap.xml')->assertOk()
            ->assertSee('https://example.test/docs', false)
            ->assertDontSee('stale-private-guide', false)
            ->assertHeader('Cache-Control', 'no-store, private');

        $source->public = false;
        $this->get('/sitemap.xml')->assertOk()
            ->assertDontSee('https://example.test/docs', false)
            ->assertDontSee('stale-private-guide', false)
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame(2, $source->reads);
    }

    public function test_ordinary_sources_still_use_the_configured_rendered_xml_cache(): void
    {
        $source = new class implements SitemapSource
        {
            public string $path = 'first';

            public int $reads = 0;

            public function urls(): iterable
            {
                $this->reads++;
                yield Url::create('https://example.test/'.$this->path);
            }
        };
        $this->app->make(SitemapSourceRegistry::class)->register($source);
        $this->get('/sitemap.xml')->assertOk()->assertSee('https://example.test/first', false);
        $source->path = 'changed';
        $response = $this->get('/sitemap.xml')->assertOk()
            ->assertSee('https://example.test/first', false)
            ->assertDontSee('https://example.test/changed', false);
        $this->assertStringNotContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame(1, $source->reads);
    }
}
