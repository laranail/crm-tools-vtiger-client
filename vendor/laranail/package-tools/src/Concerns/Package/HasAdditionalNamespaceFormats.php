<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Extra namespace formats beyond the dotted/dashed/slash set.
 *
 * For 'ichava/tabler-icons': underscore `ichava_tabler_icons`, camelCase
 * `ichavaTablerIcons`, PascalCase `IchavaTablerIcons`, kebab
 * `ichava-tabler-icons`, dotted `ichava.tabler-icons`.
 */
trait HasAdditionalNamespaceFormats
{
    /**
     * Underscore namespace: 'vendor_package'. For DB tables, cache keys,
     * env vars, snake_case function names.
     *
     * @example $package->getUnderscoreNamespace(); // 'ichava_tabler_icons'
     */
    public function getUnderscoreNamespace(): string
    {
        if ($this->configVendor === null) {
            return Str::snake(str_replace('-', '_', $this->name));
        }

        return Str::snake(
            str_replace('-', '_', $this->configVendor) . '_' .
            str_replace('-', '_', $this->name)
        );
    }

    /**
     * Snake case namespace: 'vendor_package'. Alias for getUnderscoreNamespace().
     */
    public function getSnakeCaseNamespace(): string
    {
        return $this->getUnderscoreNamespace();
    }

    /**
     * camelCase namespace: 'vendorPackage'. For JS variables, JSON keys,
     * method names.
     *
     * @example $package->getCamelCaseNamespace(); // 'ichavaTablerIcons'
     */
    public function getCamelCaseNamespace(): string
    {
        if ($this->configVendor === null) {
            return Str::camel($this->name);
        }

        return Str::camel($this->configVendor . '-' . $this->name);
    }

    /**
     * PascalCase namespace: 'VendorPackage'. For class names, component names,
     * type names.
     *
     * @example $package->getPascalCaseNamespace(); // 'IchavaTablerIcons'
     */
    public function getPascalCaseNamespace(): string
    {
        if ($this->configVendor === null) {
            return Str::studly($this->name);
        }

        return Str::studly($this->configVendor . '-' . $this->name);
    }

    /**
     * StudlyCase namespace: 'VendorPackage'. Alias for getPascalCaseNamespace().
     */
    public function getStudlyCaseNamespace(): string
    {
        return $this->getPascalCaseNamespace();
    }

    /**
     * kebab-case namespace: 'vendor-package'. Alias for getDashedNamespace();
     * for URLs and CSS classes.
     */
    public function getKebabCaseNamespace(): string
    {
        return $this->getDashedNamespace();
    }

    /**
     * Space-separated namespace: 'vendor package'. For display names,
     * titles, breadcrumbs.
     *
     * @param bool $titleCase Whether to convert to Title Case
     *
     * @example $package->getSpacedNamespace(true); // 'Ichava Tabler Icons'
     */
    public function getSpacedNamespace(bool $titleCase = false): string
    {
        if ($this->configVendor === null) {
            $result = str_replace(['-', '_'], ' ', $this->name);
        } else {
            $result = str_replace(['-', '_'], ' ', $this->configVendor . ' ' . $this->name);
        }

        return $titleCase ? Str::title($result) : $result;
    }

    /**
     * Title case namespace: 'Vendor Package'. Shortcut for
     * getSpacedNamespace(titleCase: true).
     *
     * @example $package->getTitleCaseNamespace(); // 'Ichava Tabler Icons'
     */
    public function getTitleCaseNamespace(): string
    {
        return $this->getSpacedNamespace(true);
    }

    /**
     * SCREAMING_SNAKE_CASE namespace: 'VENDOR_PACKAGE'. For constants, env
     * vars, config keys.
     *
     * @example $package->getScreamingSnakeCaseNamespace(); // 'ICHAVA_TABLER_ICONS'
     */
    public function getScreamingSnakeCaseNamespace(): string
    {
        return Str::upper($this->getUnderscoreNamespace());
    }

    /**
     * URL-safe namespace: 'vendor-package'. Alias for getKebabCaseNamespace().
     *
     * @example $package->getUrlSafeNamespace(); // 'ichava-tabler-icons'
     */
    public function getUrlSafeNamespace(): string
    {
        return $this->getKebabCaseNamespace();
    }

    /**
     * Every namespace format, keyed by format name. Handy for debugging.
     *
     * @return array<string, string>
     */
    public function getAllNamespaceFormats(): array
    {
        return [
            'dotted' => $this->getDottedNamespace(),
            'dashed' => $this->getDashedNamespace(),
            'doubleColon' => $this->getDoubleColonNamespace(),
            'slash' => $this->getSlashNamespace(),
            'underscore' => $this->getUnderscoreNamespace(),
            'camelCase' => $this->getCamelCaseNamespace(),
            'pascalCase' => $this->getPascalCaseNamespace(),
            'kebabCase' => $this->getKebabCaseNamespace(),
            'snakeCase' => $this->getSnakeCaseNamespace(),
            'screamingSnake' => $this->getScreamingSnakeCaseNamespace(),
            'spaced' => $this->getSpacedNamespace(),
            'titleCase' => $this->getTitleCaseNamespace(),
        ];
    }

    /**
     * Namespace in a format named at runtime.
     *
     * @param string $format Format name (e.g. 'camelCase', 'snake_case')
     *
     * @throws InvalidArgumentException If format is not supported
     *
     * @example $package->getNamespaceFormat('camelCase'); // 'ichavaTablerIcons'
     */
    public function getNamespaceFormat(string $format): string
    {
        $formats = [
            'dotted' => fn () => $this->getDottedNamespace(),
            'dashed' => fn () => $this->getDashedNamespace(),
            'kebab' => fn () => $this->getKebabCaseNamespace(),
            'kebab-case' => fn () => $this->getKebabCaseNamespace(),
            'doubleColon' => fn () => $this->getDoubleColonNamespace(),
            'double-colon' => fn () => $this->getDoubleColonNamespace(),
            'slash' => fn () => $this->getSlashNamespace(),
            'underscore' => fn () => $this->getUnderscoreNamespace(),
            'snake' => fn () => $this->getSnakeCaseNamespace(),
            'snake_case' => fn () => $this->getSnakeCaseNamespace(),
            'camel' => fn () => $this->getCamelCaseNamespace(),
            'camelCase' => fn () => $this->getCamelCaseNamespace(),
            'pascal' => fn () => $this->getPascalCaseNamespace(),
            'PascalCase' => fn () => $this->getPascalCaseNamespace(),
            'studly' => fn () => $this->getStudlyCaseNamespace(),
            'StudlyCase' => fn () => $this->getStudlyCaseNamespace(),
            'screaming' => fn () => $this->getScreamingSnakeCaseNamespace(),
            'SCREAMING_SNAKE' => fn () => $this->getScreamingSnakeCaseNamespace(),
            'spaced' => fn () => $this->getSpacedNamespace(),
            'title' => fn () => $this->getTitleCaseNamespace(),
            'titleCase' => fn () => $this->getTitleCaseNamespace(),
        ];

        $normalizedFormat = Str::lower(trim($format));

        if (! isset($formats[$normalizedFormat])) {
            throw new InvalidArgumentException(
                "Unsupported namespace format: '{$format}'. " .
                'Supported formats: ' . implode(', ', array_keys($formats))
            );
        }

        return $formats[$normalizedFormat]();
    }
}
