<?php

namespace Splicewire\Beam\Sitemap;

use Illuminate\Support\ServiceProvider;
use Rushing\Popcorn\Registries\RegistryIndex;
use Splicewire\Beam\Sitemap\Console\GenerateSitemapCommand;
use Splicewire\Beam\Sitemap\Resolvers\ConfigSitemapBaseUrlResolver;
use Splicewire\Beam\Sitemap\Resolvers\SitemapBaseUrlResolver;

/**
 * The sitemap arm's provider (free-tier beam family, ADR-0166). Homes the
 * SitemapSource seam + registry + the RouteSitemapSource MDX/static-page bridge +
 * the /sitemap.xml controller + the chunked-generate command — all relocated down
 * from `laravel-satellite`, which had zero satellite coupling.
 *
 * The arm owns the plumbing, never the data: consumers register their own sources
 * onto the {@see SitemapSourceRegistry} (beam-ux → EntrySitemapSource, a satellite
 * → its vertical URLs). It ships the controller but NOT a route — a host wires the
 * route into its own (possibly gated) group.
 */
class BeamSitemapServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merged at `beam.sitemap`, from config/beam/sitemap.php — the family's nested shape
        // (beam/core, beam/taxonomy, …). The key is unchanged; only the file moved. It used to ship
        // a whole `config/beam.php`, which claims the ENTIRE `beam` namespace as one file and races
        // the `config/beam/` directory every other beam package publishes into: whichever Laravel
        // loads second wins, so a host with both could silently lose either this config or theirs.
        $this->mergeConfigFrom(__DIR__.'/../config/beam/sitemap.php', 'beam.sitemap');

        // The base-URL port (ADR-0166 §2). Default returns config('app.url') —
        // single-tenant/retrofit-safe. laravel-beam-tenancy re-binds it.
        $this->app->singleton(SitemapBaseUrlResolver::class, ConfigSitemapBaseUrlResolver::class);

        // The sitemap source registry. Consumers push their own source at boot.
        $this->app->singleton(SitemapSourceRegistry::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateSitemapCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/beam/sitemap.php' => config_path('beam/sitemap.php'),
            ], 'beam-sitemap-config');
        }

        $this->registerRouteSource();

        // Declaring and indexing are two acts (registry-kernel 21 D1). SitemapSourceRegistry declares
        // `beam.sitemap.sources`; this is where that root becomes routable through the shared index.
        //
        // AFTER registerRouteSource() deliberately, and unconditionally regardless of what it did: a
        // host that has disabled the route source, or that publishes nothing at all, still OWNS this
        // branch of the keyspace (04 D1 — index membership must not be a function of host
        // composition). An empty `beam.sitemap.sources` is the correct reading of "this site serves no
        // public URLs", not a gap.
        $this->app->make(RegistryIndex::class)->describe(
            $this->app->make(SitemapSourceRegistry::class),
            by: self::class,
        );
    }

    /**
     * Register the MDX/static-page bridge (RouteSitemapSource) as the default
     * source. Gated by `beam.sitemap.enabled` (default on).
     */
    protected function registerRouteSource(): void
    {
        if (! config('beam.sitemap.enabled', true)) {
            return;
        }

        $this->app->make(SitemapSourceRegistry::class)
            ->register(RouteSitemapSource::class);
    }
}
