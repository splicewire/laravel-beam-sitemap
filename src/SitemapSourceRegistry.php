<?php

namespace Splicewire\Beam\Sitemap;

use Illuminate\Contracts\Container\Container;
use Rushing\Popcorn\Registries\Authorizer;
use Rushing\Popcorn\Registries\BasicRegistry;
use Rushing\Popcorn\Registries\Exceptions\InvalidRegistryKey;
use Rushing\Popcorn\Registries\Gated;
use Rushing\Popcorn\Registries\IsRegistry;
use Rushing\Popcorn\Registries\Key;
use Rushing\Popcorn\Registries\OnDuplicate;
use Rushing\Popcorn\Registries\Optionality;
use Rushing\Popcorn\Registries\Registry;
use Rushing\Popcorn\Registries\RegistryArity;
use Rushing\Popcorn\Registries\RegistryKey;
use Splicewire\Beam\Sitemap\Contracts\SitemapSource;

/**
 * Collects the registered SitemapSources. Consumers push their own source at boot
 * (beam-ux → EntrySitemapSource, a satellite → its vertical URLs); the arm
 * supplies the plumbing, never the data. Mirrors WebhookProfileRegistry.
 *
 * Relocated down from `laravel-satellite` (ADR-0166).
 *
 * ## Conformed to the Popcorn kernel (registry-kernel ticket 38)
 *
 * Archetype **a / `RunAll`**. Two things about this row were unlike every other one in the sweep, and
 * both are recorded here because they are the reason the declaration reads the way it does.
 *
 * ### 1. This registry had NO KEYS — it was a LIST
 *
 * `register()` took a source and appended it; nothing was ever looked up by name, because the only
 * read is "run all of them, in order". So there was no key to read a root off, which is what
 * [ticket 40]'s rule normally supplies — *a root must be a prefix of the keys its owner already
 * registers*. The keys here are therefore **minted**, and minted by the kernel's own sanctioned,
 * explicit derivation rather than by a spelling of this package's invention: {@see Key::fromClass()},
 * which is documented as the opt-in way a class becomes a key. `EntrySitemapSource` becomes
 * `beam.sitemap.sources.entry-sitemap-source`.
 *
 * A minted key is pure ADDITION — it buys the arm's sources an address in the shared index, which is
 * the whole point of the lift, and it takes nothing away, because no read ever consulted a key.
 *
 * ### 2. `OnDuplicate::Admit` is REQUIRED here, not preferred
 *
 * A PHP list appends unconditionally: registering the same thing twice yields two entries and both
 * run. `Supersede` would silently collapse them, which is a behaviour change, and it is not
 * hypothetical — the estate registers **anonymous** SitemapSources (this package's own
 * `SitemapArmTest`, and `laravel-satellite`'s sitemap tests). An anonymous class has no name a key can
 * be derived from, so every one of them lands on the shared `anonymous` segment; under `Supersede`
 * two ad-hoc sources registered in one boot would become one and half the sitemap would vanish.
 * `Admit` keeps both live under the one key and defers to read time, and under `RunAll` several
 * matches at a key are the ANSWER rather than the error, so nothing downstream has to care.
 *
 * The consequence to know: `resolve()` at a key holding more than one entry throws
 * `AmbiguousRegistryMatch`, by design. Nothing in this package resolves singly — {@see all()} and
 * {@see urls()} are the reads, and both are `matches()`-shaped.
 *
 * ### The entry type is `SitemapSource`, and the class-string is storage
 *
 * A source may be registered as an instance or as a `class-string` the container makes later, and the
 * laziness is deliberate — the arm registers `RouteSitemapSource::class` from its own `boot()`, well
 * before the container can build one. But the kernel's rule is one entry type per registry, and
 * *"where a class serves two output shapes that is a port's job, not the kernel's"* (ticket 01 D3), so
 * this port normalises: every contract read hydrates, and `entryType` says `SitemapSource` because
 * that is what a read gives you. {@see unfiltered()} is the raw store — the artisan-only doctor/gate
 * escape — and it alone yields the as-registered shape.
 */
#[IsRegistry(
    root: 'beam.sitemap.sources',
    of: 'contributors of public, canonical URLs to the beam sitemap — one per body of content a host publishes',
    arity: RegistryArity::RunAll,
    entryType: SitemapSource::class,
    onDuplicate: OnDuplicate::Admit,
    optionality: Optionality::Optional,
    note: 'Keys are MINTED from the entry class via Key::fromClass() — this registry was a keyless list '
        .'and no read has ever consulted a key. Anonymous sources (the estate registers several in '
        .'tests) share the `anonymous` segment, which is why OnDuplicate is Admit: Supersede would '
        .'collapse two ad-hoc sources registered in one boot into one, silently halving a sitemap.',
)]
class SitemapSourceRegistry implements Gated, Registry
{
    /** The key minted for a source whose class has no name a {@see Key} can be derived from. */
    public const AnonymousKey = 'anonymous';

    private BasicRegistry $entries;

    public function __construct(
        private Container $container,
    ) {
        $this->entries = BasicRegistry::for($this);
    }

    /**
     * Register a source, keyed by its own class.
     *
     * WIDENED contravariantly from the contract rather than shadowing it, so the historical
     * one-argument call (`$registry->register(EntrySitemapSource::class)`, and the instance form) keeps
     * working unchanged at every one of its call sites. The disambiguation is `$entry`: absent means
     * the single argument IS the source and mints its own key; present means the kernel's four-argument
     * form, where the first argument is the key. That matters here specifically because `string` is
     * legal in BOTH positions — a bare string is a class-string to the historical caller and a key to
     * the contract — and nothing but the arity of the call can tell them apart.
     *
     * @param  SitemapSource|class-string<SitemapSource>|RegistryKey|string  $key
     */
    public function register(RegistryKey|string|SitemapSource $key, mixed $entry = null, ?string $by = null, ?string $ability = null): static
    {
        if ($entry === null && ! $key instanceof RegistryKey) {
            $entry = $key;
            $key = $this->keyFor($key);
        }

        $this->entries->register($key, $entry, $by, $ability);

        return $this;
    }

    /**
     * Every registered source, container-made where it was registered lazily, in REGISTRATION ORDER.
     *
     * Rebuilt from `matches()` over the root rather than from `relativeKeys()` — the spine's usual
     * shape — because `keys()` de-duplicates by key identity and this registry admits duplicates at one
     * key on purpose (see the class docblock). Enumerating via the key list would drop every anonymous
     * source but the first.
     *
     * @return list<SitemapSource>
     */
    public function all(): array
    {
        return $this->matches($this->entries->root());
    }

    /**
     * Every URL from every registered source, flattened and lazy.
     *
     * @return iterable<\Spatie\Sitemap\Tags\Url|\Spatie\Sitemap\Contracts\Sitemapable>
     */
    public function urls(): iterable
    {
        foreach ($this->all() as $source) {
            // Not `yield from`: it preserves each generator's keys, so every
            // source restarts at 0 and key-preserving consumers
            // (iterator_to_array, collect) silently drop all but the last.
            foreach ($source->urls() as $url) {
                yield $url;
            }
        }
    }

    public function has(RegistryKey|string $key): bool
    {
        return $this->entries->has($key);
    }

    public function resolve(RegistryKey|string $key): SitemapSource
    {
        return $this->hydrate($this->entries->resolve($key));
    }

    public function tryResolve(RegistryKey|string $key): ?SitemapSource
    {
        $source = $this->entries->tryResolve($key);

        return $source === null ? null : $this->hydrate($source);
    }

    /**
     * @return list<SitemapSource>
     */
    public function matches(RegistryKey|string $key): array
    {
        return array_map($this->hydrate(...), $this->entries->matches($key));
    }

    /**
     * @return list<RegistryKey>
     */
    public function keys(): array
    {
        return $this->entries->keys();
    }

    /**
     * ⚠️ The RAW store: its reads yield the as-registered shape (a class-string is NOT made), unlike
     * every read on this port. That is the honest answer for the doctor and the surgeon gate, which
     * are asking what was registered rather than what it resolves to, and both are artisan-only.
     */
    public function unfiltered(): Registry
    {
        return $this->entries->unfiltered();
    }

    public function authorizeWith(?Authorizer $authorizer): static
    {
        $this->entries->authorizeWith($authorizer);

        return $this;
    }

    /**
     * Mint the relative key a source registers itself under.
     *
     * `Key::fromClass()` THROWS where the old raw array append was quiet (sweep amendment A3), and the
     * live case is not exotic: an anonymous class is named `SitemapSource@anonymous/path/to/file.php:9$0`,
     * whose `@`, `/` and `$` are all illegal key characters. Those sources are real and registered in
     * this estate, so they get a shared, named segment rather than an exception — legible in
     * `popcorn:registries` as exactly what it is.
     *
     * @param  SitemapSource|class-string<SitemapSource>|string  $source
     */
    private function keyFor(SitemapSource|string $source): string
    {
        $class = $source instanceof SitemapSource ? $source::class : $source;

        try {
            return (string) Key::fromClass($class);
        } catch (InvalidRegistryKey) {
            return self::AnonymousKey;
        }
    }

    /**
     * @param  SitemapSource|class-string<SitemapSource>  $source
     */
    private function hydrate(SitemapSource|string $source): SitemapSource
    {
        return $source instanceof SitemapSource ? $source : $this->container->make($source);
    }
}
