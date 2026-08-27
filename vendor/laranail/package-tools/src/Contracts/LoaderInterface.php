<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Contracts;

/**
 * Loads resources such as components, helpers, and translations.
 */
interface LoaderInterface
{
    /**
     * @param string $path Path to resource
     */
    public function load(string $path): void;

    /**
     * @param string $path Path to check
     */
    public function canLoad(string $path): bool;

    /**
     * @return array<string>
     */
    public function getLoaded(): array;
}
