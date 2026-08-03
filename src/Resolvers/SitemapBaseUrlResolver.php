<?php

namespace Splicewire\Beam\Sitemap\Resolvers;

/**
 * The port that answers "what absolute base URL do sitemap URLs hang off?"
 * (ADR-0166 §2 — "base URL is a port, not a constant").
 *
 * The default binding ({@see ConfigSitemapBaseUrlResolver}) returns
 * `config('app.url')`, so a single-tenant / retrofit host works out of the box
 * (honoring ADR-0151's retrofit stance: the base must not require tenancy).
 * `laravel-beam-tenancy` owns the domain→tenant mapping, so it re-binds this
 * port to return the active tenant's domain — a beam-internal composition, not a
 * satellite concern.
 */
interface SitemapBaseUrlResolver
{
    /**
     * The absolute base URL, with no trailing slash (e.g. `https://example.test`).
     */
    public function baseUrl(): string;
}
