> You are in **splicewire/laravel-beam-sitemap** — the sitemap arm of the schemastud beam family.

A Laravel package providing the `SitemapSource` contract, `SitemapSourceRegistry`, a
`/sitemap.xml` controller, a chunked-generate command, and the `RouteSitemapSource`
MDX/static-page bridge. Free-tier by design: pure Illuminate + `spatie/laravel-sitemap`, with no
upward reach into `satellite`/`beam-ux`/`tower`. Consumers register their own sources onto the
registry; this package owns only the plumbing, never the data.
