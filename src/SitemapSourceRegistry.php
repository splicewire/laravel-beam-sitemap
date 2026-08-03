<?php

namespace Splicewire\Beam\Sitemap;

use Illuminate\Contracts\Container\Container;
use Splicewire\Beam\Sitemap\Contracts\SitemapSource;

/**
 * Collects the registered SitemapSources. Consumers push their own source at boot
 * (beam-ux → EntrySitemapSource, a satellite → its vertical URLs); the arm
 * supplies the plumbing, never the data. Mirrors WebhookProfileRegistry.
 *
 * Relocated down from `laravel-satellite` (ADR-0166).
 */
class SitemapSourceRegistry
{
    /** @var list<SitemapSource|class-string<SitemapSource>> */
    private array $sources = [];

    public function __construct(
        private Container $container,
    ) {}

    /**
     * @param  SitemapSource|class-string<SitemapSource>  $source
     */
    public function register(SitemapSource|string $source): void
    {
        $this->sources[] = $source;
    }

    /**
     * @return list<SitemapSource>
     */
    public function all(): array
    {
        return array_map(
            fn (SitemapSource|string $source): SitemapSource => $source instanceof SitemapSource
                ? $source
                : $this->container->make($source),
            $this->sources,
        );
    }

    /**
     * Every URL from every registered source, flattened and lazy.
     *
     * @return iterable<\Spatie\Sitemap\Tags\Url|\Spatie\Sitemap\Contracts\Sitemapable>
     */
    public function urls(): iterable
    {
        foreach ($this->all() as $source) {
            // Not `yield from`: it preserves each generator's keys, so every
            // source restarts at 0 and key-preserving consumers
            // (iterator_to_array, collect) silently drop all but the last.
            foreach ($source->urls() as $url) {
                yield $url;
            }
        }
    }
}
