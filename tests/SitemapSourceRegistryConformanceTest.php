<?php

namespace Splicewire\Beam\Sitemap\Tests;

use Rushing\Popcorn\Registries\Registry;
use Rushing\Popcorn\Registries\RegistryIndex;
use Spatie\Sitemap\Tags\Url;
use Splicewire\Beam\Sitemap\Contracts\SitemapSource;
use Splicewire\Beam\Sitemap\RouteSitemapSource;
use Splicewire\Beam\Sitemap\SitemapSourceRegistry;
use Splicewire\Beam\Sitemap\Tests\Fakes\FakeSitemapSource;

/**
 * The kernel half of {@see SitemapArmTest} — registry-kernel ticket 38, archetype a / RunAll. That
 * file asserts the arm's behaviour is unchanged by the migration; this one asserts the migration
 * happened at all, which is a separate claim and the one that fails silently.
 */
class SitemapSourceRegistryConformanceTest extends TestCase
{
    /**
     * The tripwire (27 D3). A package whose harness omits PopcornServiceProvider gets a fresh
     * RegistryIndex per make(), so every describe() lands on a throwaway and every assertion below
     * passes vacuously over an empty index. Assert the sharing before believing any of them.
     */
    public function test_the_registry_index_is_a_shared_singleton(): void
    {
        $this->assertSame($this->app->make(RegistryIndex::class), $this->app->make(RegistryIndex::class));
    }

    public function test_the_registry_conforms_to_the_kernel_contract(): void
    {
        $this->assertInstanceOf(Registry::class, $this->app->make(SitemapSourceRegistry::class));
    }

    public function test_the_provider_describes_the_root_into_the_shared_index(): void
    {
        $keys = array_map(strval(...), $this->app->make(RegistryIndex::class)->keys());

        $this->assertContains('beam.sitemap.sources', $keys);
    }

    public function test_an_absolute_source_key_routes_back_to_the_registry(): void
    {
        $this->assertSame(
            $this->app->make(SitemapSourceRegistry::class),
            $this->app->make(RegistryIndex::class)->routeTo('beam.sitemap.sources.route-sitemap-source'),
        );
    }

    /**
     * The round trip through the port's OWN vocabulary — the one-argument class-string form the arm
     * and every host use — read back through the minted key, and the key's spelling in both
     * directions: relative in, absolute out.
     */
    public function test_a_class_string_registration_round_trips_and_mints_its_key(): void
    {
        $registry = $this->app->make(SitemapSourceRegistry::class);

        $registry->register(FakeSitemapSource::class);

        $this->assertTrue($registry->has('fake-sitemap-source'));

        // Registered lazily as a class-string; a contract read hands back the MADE object, because the
        // port normalises the two storage shapes to the one declared entryType (ticket 01 D3).
        $this->assertInstanceOf(FakeSitemapSource::class, $registry->resolve('fake-sitemap-source'));

        $this->assertContains(
            'beam.sitemap.sources.fake-sitemap-source',
            array_map(strval(...), $registry->keys()),
        );
    }

    /**
     * ⚠️ The load-bearing one for a `RunAll` row: `keys()`'s documented guarantee is REGISTRATION
     * ORDER, and this registry's only read runs every source in it, so the order is observable in the
     * emitted sitemap. The arm's own RouteSitemapSource is registered from the provider and must stay
     * first.
     */
    public function test_sources_run_in_registration_order(): void
    {
        $registry = $this->app->make(SitemapSourceRegistry::class);

        $registry->register(FakeSitemapSource::class);
        $registry->register($anonymous = $this->anonymousSource('https://example.test/anon'));

        $this->assertSame(
            [RouteSitemapSource::class, FakeSitemapSource::class, $anonymous::class],
            array_map(fn (SitemapSource $s): string => $s::class, $registry->all()),
        );
    }

    /**
     * Sweep amendment A4 says re-registering an existing key SUPERSEDES AND APPENDS, moving the entry
     * to the end where a plain array assignment held its slot. **That does not apply to this row**, and
     * the difference is worth pinning rather than assuming: this registry declares
     * `OnDuplicate::Admit`, so a second registration at the same key does not displace the first —
     * both stay live, in registration order, which is exactly what the list it replaced did.
     *
     * This is not academic. Every anonymous source in the estate lands on the one `anonymous` key, so
     * under `Supersede` two ad-hoc sources registered in one boot would silently become one.
     */
    public function test_two_anonymous_sources_share_a_key_and_both_stay_live(): void
    {
        $registry = $this->app->make(SitemapSourceRegistry::class);

        $registry->register($this->anonymousSource('https://example.test/first'));
        $registry->register($this->anonymousSource('https://example.test/second'));

        // One KEY — they are indistinguishable to the keyspace, and honestly so.
        $this->assertSame(
            ['beam.sitemap.sources.route-sitemap-source', 'beam.sitemap.sources.anonymous'],
            array_map(strval(...), $registry->keys()),
        );

        // Two ENTRIES, in order, both of which run.
        $urls = array_map(fn (Url $u): string => $u->url, iterator_to_array($registry->urls(), false));

        $this->assertSame(['https://example.test/first', 'https://example.test/second'], $urls);
    }

    private function anonymousSource(string $url): SitemapSource
    {
        return new class($url) implements SitemapSource
        {
            public function __construct(private string $url) {}

            public function urls(): iterable
            {
                yield Url::create($this->url);
            }
        };
    }
}
