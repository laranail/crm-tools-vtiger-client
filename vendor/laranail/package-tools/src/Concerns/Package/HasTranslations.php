<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use RuntimeException;

trait HasTranslations
{
    public bool $hasTranslations = false;

    /**
     * Optional short namespace alias registered alongside the full
     * vendor/package one (e.g. 'license-kit' → trans('license-kit::file.key')).
     */
    public ?string $translationAlias = null;

    /**
     * Register translations, namespaced as vendor-package.
     *
     * The optional alias registers an ADDITIONAL namespace verbatim. Prefer
     * leaving it null: a bare alias re-introduces the collision the namespaced
     * form exists to prevent, because Laravel keeps translation namespaces in a
     * flat map and the second package to claim a key silently replaces the first.
     *
     * @example
     * $package->setName('acme/widget')->hasTranslations();
     * // trans('acme-widget::messages.welcome')
     */
    public function hasTranslations(?string $alias = null): static
    {
        $this->hasTranslations = true;
        $this->translationAlias = $alias;

        return $this;
    }

    public function getTranslationAlias(): ?string
    {
        return $this->translationAlias;
    }

    /**
     * The `vendor/package` translation namespace (e.g. `laranail/validation`),
     * so `__('laranail/validation::messages.x')` names the composer package that
     * ships the string. Vendor is required.
     *
     * The slash is safe here, and the nesting it causes is the point rather than
     * a hazard: Laravel interpolates the namespace into the override path itself
     * (`FileLoader::loadNamespaceOverrides()` reads
     * `{$path}/vendor/{$namespace}/{$locale}/{$group}.php`), so the published
     * files land in `lang/vendor/laranail/validation` and are read from exactly
     * there. Publishing groups a vendor's packages under one directory instead of
     * scattering them across the `lang/vendor` root.
     *
     * Blade component tags are the one registry that cannot take this form -- see
     * Package::componentPrefix().
     *
     * @throws RuntimeException If vendor is not set
     */
    public function translationNamespace(): string
    {
        if ($this->configVendor === null) {
            throw new RuntimeException(
                'Translation namespace requires vendor/package format. ' .
                'Please use $package->setName("vendor/package") instead of just "package".'
            );
        }

        return $this->getSlashNamespace();
    }

    abstract public function getSlashNamespace(): string;
}
