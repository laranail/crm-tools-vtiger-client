<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use RuntimeException;

trait HasViews
{
    public bool $hasViews = false;

    public ?string $viewNamespace = null;

    /**
     * Register views. Defaults the namespace to `vendor/package`.
     *
     * Passing a custom namespace overrides that. Prefer not to: Laravel keeps
     * view namespaces in a flat hint map, so a bare slug like `icons` is a
     * plausible collision with a sibling package, a third-party one, or the
     * consuming application's own — and the loser is replaced silently, surfacing
     * much later as a missing view.
     *
     * @param string|null $namespace Custom view namespace (overrides the default)
     *
     * @example
     * $package->setName('acme/widget')->hasViews();
     * // view('acme/widget::view-name'), <x-acme-widget::component />
     */
    public function hasViews(?string $namespace = null): static
    {
        $this->hasViews = true;

        // Leave null when not explicitly given so viewNamespace() resolves the
        // vendor-scoped default. Assigning the short name here would make the
        // bare slug the effective default and strand the fallback below.
        $this->viewNamespace = $namespace;

        return $this;
    }

    /**
     * The view namespace. A custom namespace wins; otherwise `vendor/package`,
     * which requires a vendor, so `view('laranail/atlas::page')` names the
     * composer package that ships the file.
     *
     * Blade component tags cannot use this form. Their name pattern is
     * `x[-\:]([\w\-\:\.]*)`, which admits no forward slash, so
     * `<x-laranail/atlas::card />` truncates at the slash and is emitted as
     * literal text rather than compiled. componentPrefix() returns the hyphen
     * form for that registry, and the provider registers it as an alias over
     * these same resolved paths.
     *
     * @throws RuntimeException If vendor is not set
     */
    public function viewNamespace(): string
    {
        if ($this->viewNamespace !== null) {
            return $this->viewNamespace;
        }

        if ($this->configVendor === null) {
            throw new RuntimeException(
                'View namespace requires vendor/package format. ' .
                'Please use $package->setName("vendor/package") instead of just "package".'
            );
        }

        return $this->getSlashNamespace();
    }

    /**
     * The `vendor-package` prefix for Blade component tags, aliasing the view
     * namespace above for the one registry whose parser rejects a slash.
     *
     * A custom view namespace is mirrored rather than overridden, so a package
     * that opts out of the default still gets a tag-safe prefix.
     */
    public function componentPrefix(): string
    {
        return str_replace('/', '-', $this->viewNamespace());
    }

    abstract public function getSlashNamespace(): string;
}
