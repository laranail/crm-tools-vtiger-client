<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Contracts;

/**
 * Publishes resources such as assets, configs, and views.
 */
interface PublisherInterface
{
    /**
     * @param string $source Source path
     * @param string $target Target path
     * @param string $tag Publish tag for vendor:publish command
     */
    public function publish(string $source, string $target, string $tag): void;

    /**
     * @param string $source Source path to check
     */
    public function canPublish(string $source): bool;

    /**
     * @return array<string, string> Array of source => target paths
     */
    public function getPublished(): array;
}
