<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

/**
 * What a package says about itself beyond what `composer.json` already declares.
 *
 * Description, authors, homepage and licence are read from the package's own manifest — a package
 * author keeps that correct in order to publish at all, and asking for the same facts here would be
 * a second copy free to drift from the one composer enforces.
 *
 * These are the things a manifest cannot say: what the package does once it is *booted*, where its
 * documentation lives if that is not the homepage, and how finished it is. Each overrides the
 * manifest where a package genuinely wants to say something different at runtime.
 */
trait HasAbout
{
    public ?string $summary = null;

    public ?string $documentationUrl = null;

    public ?string $stability = null;

    /** @var list<string> */
    public array $maintainers = [];

    /**
     * A one-line description of what this package does, overriding `composer.json`'s.
     *
     * Worth setting when the manifest description is written for a package index and the useful
     * thing to say to somebody reading a list of what is installed is different.
     *
     * @example $package->describedAs('Vendor-scoped Artisan commands and publish tags.');
     */
    public function describedAs(string $summary): static
    {
        $this->summary = $summary;

        return $this;
    }

    /**
     * Where the documentation lives, when that is not the manifest's `homepage` or `support.docs`.
     */
    public function documentedAt(string $url): static
    {
        $this->documentationUrl = $url;

        return $this;
    }

    /**
     * How finished the package is — `stable`, `beta`, `experimental`, or whatever a project uses.
     *
     * Deliberately a free string rather than an enum: this is reported, never branched on, and an
     * enum would make a package's own vocabulary this package's business.
     */
    public function withStability(string $stability): static
    {
        $this->stability = $stability;

        return $this;
    }

    /**
     * Who maintains it, overriding `composer.json`'s `authors`.
     */
    public function maintainedBy(string ...$maintainers): static
    {
        $this->maintainers = array_values(array_filter(
            $maintainers,
            static fn (string $name): bool => $name !== '',
        ));

        return $this;
    }
}
