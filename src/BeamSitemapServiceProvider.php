<?php

namespace Splicewire\Beam\Sitemap;

use Illuminate\Support\ServiceProvider;
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
        $this->mergeConfigFrom(__DIR__.'/../config/beam.php', 'beam');

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
                __DIR__.'/../config/beam.php' => config_path('beam.php'),
            ], 'beam-sitemap-config');
        }

        $this->registerRouteSource();
        $this->registerAliases();
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

    /**
     * BC net for the pre-relocation `Splicewire\Satellite\Sitemap\*` FQCNs
     * (ADR-0166; mirrors the recohere down-move class_alias pattern). Any host or
     * test still type-hinting the old satellite FQCNs resolves to the new arm's
     * classes. Guarded so a real `laravel-satellite` install (which keeps only its
     * vertical sources) never double-declares.
     */
    protected function registerAliases(): void
    {
        $map = [
            \Splicewire\Beam\Sitemap\Contracts\SitemapSource::class => 'Splicewire\\Satellite\\Sitemap\\Contracts\\SitemapSource',
            \Splicewire\Beam\Sitemap\SitemapSourceRegistry::class => 'Splicewire\\Satellite\\Sitemap\\SitemapSourceRegistry',
            \Splicewire\Beam\Sitemap\RouteSitemapSource::class => 'Splicewire\\Satellite\\Sitemap\\RouteSitemapSource',
            \Splicewire\Beam\Sitemap\Http\SitemapController::class => 'Splicewire\\Satellite\\Sitemap\\Http\\SitemapController',
            \Splicewire\Beam\Sitemap\Console\GenerateSitemapCommand::class => 'Splicewire\\Satellite\\Sitemap\\Console\\GenerateSitemapCommand',
        ];

        foreach ($map as $new => $old) {
            if (! class_exists($old) && ! interface_exists($old)) {
                class_alias($new, $old);
            }
        }
    }
}
