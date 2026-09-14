<?php

namespace Splicewire\Beam\Sitemap\Contracts;

/**
 * A source whose current visibility must be evaluated on every sitemap request.
 *
 * The combined XML cannot be cached when any source implements this contract:
 * cached URLs would bypass a changed access policy, even if that source now yields nothing.
 */
interface LiveSitemapSource extends SitemapSource {}
