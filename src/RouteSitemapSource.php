<?php

namespace Splicewire\Beam\Sitemap;

use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use Spatie\Sitemap\Tags\Url;
use Splicewire\Beam\Sitemap\Contracts\SitemapSource;
use Splicewire\Beam\Sitemap\Resolvers\SitemapBaseUrlResolver;

/**
 * The MDX / static-page bridge. Every build-time MDX page is already a named
 * Laravel route (`content.*`), so PHP enumerates the whole MDX plane from the
 * route table without reading the JS content glob. Also covers static index
 * pages (`home`, section indexes). Parametric ({param}) routes are skipped —
 * an unbound placeholder can't yield a concrete canonical URL.
 *
 * Relocated down from `laravel-satellite` (ADR-0166); the config prefix moved
 * `splicewire.satellite.sitemap.*` → `beam.sitemap.*`, and the base URL now comes
 * from the {@see SitemapBaseUrlResolver} port instead of a raw `config('app.url')`
 * read.
 *
 * Once MDX pages are BeamUxEntry rows, the EntrySitemapSource's real publish-state
 * filtering supersedes this route-table heuristic (ADR-0166 §Consequences).
 */
class RouteSitemapSource implements SitemapSource
{
    public function __construct(
        private Router $router,
        private SitemapBaseUrlResolver $baseUrl,
    ) {}

    public function urls(): iterable
    {
        /** @var list<string> $patterns */
        $patterns = (array) config('beam.sitemap.route_name_patterns', ['home', 'content.*']);
        $base = $this->baseUrl->baseUrl();

        // Fluent ->name() lands after RouteCollection::add(), so the name list
        // is stale for any route registered post-boot; refresh before reading.
        $routes = $this->router->getRoutes();
        $routes->refreshNameLookups();

        foreach ($routes->getRoutesByName() as $name => $route) {
            if (! $this->matches($name, $patterns)) {
                continue;
            }

            if (str_contains($route->uri(), '{')) {
                continue;
            }

            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            yield Url::create($base.'/'.ltrim($route->uri(), '/'));
        }
    }

    /**
     * @param  list<string>  $patterns
     */
    private function matches(string $name, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $name)) {
                return true;
            }
        }

        return false;
    }
}
