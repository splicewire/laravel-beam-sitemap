# laravel-beam-sitemap

The **sitemap arm** of the schemastud beam family (free-tier, [ADR-0166]). A sitemap is
intrinsic to *any* beam site that serves public content, so the seam homes at the tier of the
content it enumerates — not up at the consumer-app backend where it used to live.

Relocated **down** from `laravel-satellite` (which had zero satellite coupling):

- `Contracts\SitemapSource` — the one-method contributor contract.
- `SitemapSourceRegistry` — collects registered sources; the arm owns the plumbing, never the data.
- `RouteSitemapSource` — the MDX/static-page bridge (enumerates `content.*` / `home` named routes).
- `Http\SitemapController` — renders `/sitemap.xml` from every registered source (cached).
- `Console\GenerateSitemapCommand` — `splicewire:beam:sitemap:generate`, the chunked large-catalog escape hatch.

## The base-URL port

Absolute canonical URLs hang off `SitemapBaseUrlResolver`. The default binding
(`ConfigSitemapBaseUrlResolver`) returns `config('app.url')` — single-tenant / retrofit-safe, no
tenancy dependency. `laravel-beam-tenancy` re-binds the port to the active tenant's domain.

## Registering sources

Consumers push their own source onto the registry at boot:

```php
app(SitemapSourceRegistry::class)->register(MyVerticalSitemapSource::class);
```

- `laravel-beam-ux` registers `EntrySitemapSource` (entries gated by route × published-marking × entitlement).
- `laravel-satellite` serves the gated `/sitemap.xml` and `/robots.txt` routes and registers no source of its
  own; each satellite app registers its vertical or generated-page sources.

Config lives under `beam.sitemap.*` (moved from `splicewire.satellite.sitemap.*`).

## Registries

This package declares one registry:

- [`beam.sitemap.sources`](docs/registries/beam.sitemap.sources.mdx): the sources whose URLs make up
  `/sitemap.xml`. The package registers `RouteSitemapSource` into it while `beam.sitemap.enabled` is on.

It registers into one registry it doesn't own:

- [`beam.install.steps`](https://github.com/splicewire/laravel-beam/blob/main/docs/registries/beam.install.steps.mdx),
  owned by `splicewire/laravel-beam`: the install step that publishes the sitemap config. It registers only when
  Beam core is installed.

[ADR-0166]: the sitemap seam is a beam-family arm, not a satellite concern.
