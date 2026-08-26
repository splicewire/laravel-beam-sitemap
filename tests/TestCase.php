<?php

namespace Splicewire\Beam\Sitemap\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Rushing\Popcorn\Laravel\PopcornServiceProvider;
use Splicewire\Beam\Sitemap\BeamSitemapServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * The arm is a lean, free-tier seam: pure Illuminate + Spatie, no beam-core
     * or DB rung. It boots standalone.
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            // laravel-popcorn binds RegistryIndex as a SINGLETON, and testbench does NOT auto-discover
            // it — requiring the package is not enough. Without this the index is auto-resolvable but
            // UNSHARED, so BeamSitemapServiceProvider::boot()'s describe() lands on a throwaway, every
            // index assertion silently tests an empty index, and the suite stays green over a registry
            // nothing can route to (registry-kernel 27 D3).
            PopcornServiceProvider::class,
            BeamSitemapServiceProvider::class,
        ];
    }
}
