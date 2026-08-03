<?php

namespace Splicewire\Beam\Sitemap\Console;

use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\SitemapIndex;
use Splicewire\Beam\Sitemap\SitemapSourceRegistry;

/**
 * The large-catalog escape hatch. Drains the SAME registered sources the live
 * route uses, chunks them at 10k URLs/file, writes public/sitemap-N.xml, and a
 * public/sitemap.xml SitemapIndex referencing them. Static files are served by
 * the web server before Laravel, so this trades a live route's gate-awareness
 * for scale — acceptable for a large-catalog site, which is live anyway.
 *
 * Relocated down from `laravel-satellite` (ADR-0166). The command signature moved
 * `splicewire:sitemap:generate` → `beam:sitemap:generate` to mirror the package
 * tree (ADR-0167).
 */
class GenerateSitemapCommand extends Command
{
    protected $signature = 'beam:sitemap:generate {--chunk=10000 : Maximum URLs per sitemap file}';

    protected $description = 'Write a chunked sitemap + index to public/ from the registered sources (large-catalog escape hatch; bypasses any live-route site-mode gate).';

    public function handle(SitemapSourceRegistry $registry): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));
        $public = public_path();

        $index = SitemapIndex::create();
        $sitemap = Sitemap::create();
        $chunk = 0;
        $count = 0;

        $flush = function () use (&$sitemap, &$chunk, &$count, $public, $index): void {
            if ($count === 0) {
                return;
            }

            $chunk++;
            $file = 'sitemap-'.$chunk.'.xml';
            $sitemap->writeToFile($public.'/'.$file);
            $index->add(url('/'.$file));

            $sitemap = Sitemap::create();
            $count = 0;
        };

        foreach ($registry->urls() as $url) {
            $sitemap->add($url);

            if (++$count >= $chunkSize) {
                $flush();
            }
        }

        $flush();

        $index->writeToFile($public.'/sitemap.xml');

        $this->info("Wrote {$chunk} sitemap chunk(s) + a sitemap.xml index to {$public}.");

        return self::SUCCESS;
    }
}
