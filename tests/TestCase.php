<?php

namespace Splicewire\Beam\Sitemap\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
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
            BeamSitemapServiceProvider::class,
        ];
    }
}
