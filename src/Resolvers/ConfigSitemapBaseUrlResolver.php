<?php

namespace Splicewire\Beam\Sitemap\Resolvers;

/**
 * The default {@see SitemapBaseUrlResolver}: returns `config('app.url')` with any
 * trailing slash stripped. Single-tenant / retrofit-safe — no tenancy dependency
 * (ADR-0166 §2). A multi-domain host (laravel-beam-tenancy) re-binds the port.
 */
class ConfigSitemapBaseUrlResolver implements SitemapBaseUrlResolver
{
    public function baseUrl(): string
    {
        return rtrim((string) config('app.url'), '/');
    }
}
