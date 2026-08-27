<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

/**
 * Works with deeply nested package directory structures.
 */
trait HasNestedLevels
{
    protected int $nestingLevel = 0;

    protected ?string $rootLevelPath = null;

    /**
     * Set root level for nested packages
     *
     * @param int $level Nesting level (0 = root)
     */
    public function setRootLevel(int $level): static
    {
        $this->nestingLevel = $level;

        return $this;
    }

    /**
     * Set custom root level path
     *
     * @param string $path Root path
     */
    public function setRootLevelPath(string $path): static
    {
        $this->rootLevelPath = $path;

        return $this;
    }

    /**
     * Get root level path
     */
    public function getRootLevelPath(): string
    {
        if ($this->rootLevelPath) {
            return $this->rootLevelPath;
        }

        $basePath = $this->packageBasePath();

        for ($i = 0; $i < $this->nestingLevel; $i++) {
            $basePath = dirname((string) $basePath);
        }

        return $basePath;
    }

    /**
     * Get nesting level
     */
    public function getNestingLevel(): int
    {
        return $this->nestingLevel;
    }

    /**
     * Resolve path relative to root level
     *
     * @param string $path Path to resolve
     */
    public function resolveFromRoot(string $path): string
    {
        $rootPath = $this->getRootLevelPath();

        return rtrim((string) $rootPath, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Check if package is nested
     */
    public function isNested(): bool
    {
        return $this->nestingLevel > 0;
    }

    /**
     * Get package base path
     *
     * @param string $path Optional path to append
     */
    abstract protected function packageBasePath(string $path = ''): string;
}
