<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sitemap
    |--------------------------------------------------------------------------
    |
    | A live, cached /sitemap.xml, served from the `web` group so a host that
    | gates its site (e.g. a Marquee `soon` mode) short-circuits crawlers
    | pre-launch and flips live with no redeploy. `path` is the sitemap route;
    | `route_name_patterns` are the named-route globs the RouteSitemapSource
    | enumerates (the MDX/static-page bridge — `content.*` pages plus `home`).
    | Consumers (a beam-ux EntrySitemapSource, a satellite's vertical sources)
    | contribute their own URLs by registering a source on the arm's registry.
    |
    | Config prefix moved `splicewire.satellite.sitemap.*` → `beam.sitemap.*`
    | (ADR-0166) when the seam relocated down from laravel-satellite.
    |
    */
    'sitemap' => [
        'enabled' => true,
        'path' => 'sitemap.xml',
        'cache_ttl' => 3600,
        'route_name_patterns' => ['home', 'content.*'],
    ],

];
