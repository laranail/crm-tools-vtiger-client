<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Contracts;

/**
 * Tracks registered items such as components, events, and middleware.
 */
interface RegistryInterface
{
    /**
     * @param string $key Unique identifier
     * @param mixed $value Value to register
     */
    public function register(string $key, mixed $value): void;

    /**
     * @return array<string, mixed>
     */
    public function getRegistered(): array;

    /**
     * @param string $key Item identifier
     */
    public function has(string $key): bool;

    /**
     * @param string $key Item identifier
     * @param mixed $default Default value if not found
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * @param string $key Item identifier
     */
    public function unregister(string $key): void;
}
