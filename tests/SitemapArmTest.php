<?php

namespace Splicewire\Beam\Sitemap\Tests;

use Spatie\Sitemap\Tags\Url;
use Splicewire\Beam\Sitemap\Contracts\SitemapSource;
use Splicewire\Beam\Sitemap\Resolvers\ConfigSitemapBaseUrlResolver;
use Splicewire\Beam\Sitemap\Resolvers\SitemapBaseUrlResolver;
use Splicewire\Beam\Sitemap\RouteSitemapSource;
use Splicewire\Beam\Sitemap\SitemapSourceRegistry;

class SitemapArmTest extends TestCase
{
    public function test_base_url_resolver_defaults_to_app_url_without_trailing_slash(): void
    {
        config()->set('app.url', 'https://example.test/');

        $this->assertSame(
            'https://example.test',
            $this->app->make(SitemapBaseUrlResolver::class)->baseUrl(),
        );

        $this->assertInstanceOf(
            ConfigSitemapBaseUrlResolver::class,
            $this->app->make(SitemapBaseUrlResolver::class),
        );
    }

    public function test_registry_composes_urls_from_every_registered_source(): void
    {
        $registry = $this->app->make(SitemapSourceRegistry::class);

        $registry->register(new class implements SitemapSource
        {
            public function urls(): iterable
            {
                yield Url::create('https://example.test/a');
                yield Url::create('https://example.test/b');
            }
        });

        $registry->register(new class implements SitemapSource
        {
            public function urls(): iterable
            {
                yield Url::create('https://example.test/c');
            }
        });

        // The RouteSitemapSource default source contributes nothing here (no
        // content.* routes registered), so only the two ad-hoc sources yield.
        $urls = array_map(fn (Url $u) => $u->url, iterator_to_array($registry->urls(), false));

        $this->assertContains('https://example.test/a', $urls);
        $this->assertContains('https://example.test/b', $urls);
        $this->assertContains('https://example.test/c', $urls);
    }

    public function test_route_source_is_registered_as_a_default_source(): void
    {
        $registry = $this->app->make(SitemapSourceRegistry::class);

        $classes = array_map(fn ($s) => $s::class, $registry->all());

        $this->assertContains(RouteSitemapSource::class, $classes);
    }

    public function test_route_source_enumerates_named_content_routes_and_skips_parametric(): void
    {
        config()->set('app.url', 'https://example.test');
        config()->set('beam.sitemap.route_name_patterns', ['home', 'content.*']);

        $this->app['router']->get('/', fn () => '')->name('home');
        $this->app['router']->get('/guides/intro', fn () => '')->name('content.guides.intro');
        $this->app['router']->get('/posts/{slug}', fn () => '')->name('content.posts.show');
        $this->app['router']->get('/admin', fn () => '')->name('admin.dashboard');

        $source = $this->app->make(RouteSitemapSource::class);
        $urls = array_map(fn (Url $u) => $u->url, iterator_to_array($source->urls(), false));

        $this->assertContains('https://example.test/', $urls);
        $this->assertContains('https://example.test/guides/intro', $urls);
        // Parametric route skipped (unbound placeholder can't yield a canonical URL).
        $this->assertNotContains('https://example.test/posts/{slug}', $urls);
        // Non-matching name skipped.
        $this->assertNotContains('https://example.test/admin', $urls);
    }
}
