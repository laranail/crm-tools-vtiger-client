<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Asset;

/**
 * One publish tag: the paths it publishes, the package that owns it, and
 * whether that package asked for the destination to be cleaned first.
 */
final readonly class PublishTagEntry
{
    /**
     * @param array<string, string> $paths source => destination
     */
    public function __construct(
        public string $tag,
        public string $package,
        public array $paths,
        public bool $cleanable = false,
    ) {}

    /**
     * A copy with more paths folded in.
     *
     * A tag can be published from several call sites — a package may declare
     * assets through the fluent builder and again through the asset registry —
     * so repeat records merge rather than replace. `cleanable` is sticky: one
     * call site asking for a clean is enough.
     *
     * @param array<string, string> $paths source => destination
     */
    public function merge(array $paths, bool $cleanable = false): self
    {
        return new self(
            tag: $this->tag,
            package: $this->package,
            paths: [...$this->paths, ...$paths],
            cleanable: $this->cleanable || $cleanable,
        );
    }

    /**
     * Where this tag publishes to.
     *
     * @return list<string>
     */
    public function destinations(): array
    {
        return array_values(array_unique($this->paths));
    }

    /**
     * Where this tag publishes from.
     *
     * @return list<string>
     */
    public function sources(): array
    {
        return array_values(array_unique(array_keys($this->paths)));
    }
}
