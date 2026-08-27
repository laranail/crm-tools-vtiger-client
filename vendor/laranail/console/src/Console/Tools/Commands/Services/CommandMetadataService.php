<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Commands\Services;

/**
 * Command Metadata Service
 *
 * Handles metadata management for console commands.
 * Provides a clean interface for storing and retrieving command-specific data.
 */
class CommandMetadataService
{
    /**
     * Command metadata storage
     */
    protected array $metadata = [];

    /**
     * Add metadata to the command
     */
    public function add(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;

        return $this;
    }

    /**
     * Add multiple metadata entries at once
     */
    public function addMany(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);

        return $this;
    }

    /**
     * Get metadata value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Get all metadata
     */
    public function all(): array
    {
        return $this->metadata;
    }

    /**
     * Check if metadata key exists
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->metadata);
    }

    /**
     * Remove metadata by key
     */
    public function remove(string $key): self
    {
        unset($this->metadata[$key]);

        return $this;
    }

    /**
     * Clear all metadata
     */
    public function clear(): self
    {
        $this->metadata = [];

        return $this;
    }

    /**
     * Get metadata count
     */
    public function count(): int
    {
        return count($this->metadata);
    }

    /**
     * Check if metadata is empty
     */
    public function isEmpty(): bool
    {
        return $this->metadata === [];
    }

    /**
     * Get metadata keys
     */
    public function keys(): array
    {
        return array_keys($this->metadata);
    }

    /**
     * Get metadata values
     */
    public function values(): array
    {
        return array_values($this->metadata);
    }

    /**
     * Filter metadata by callback
     */
    public function filter(callable $callback): array
    {
        return array_filter($this->metadata, $callback, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Map metadata values
     */
    public function map(callable $callback): array
    {
        return array_map($callback, $this->metadata);
    }

    /**
     * Merge with another metadata service
     */
    public function merge(CommandMetadataService $other): self
    {
        $this->metadata = array_merge($this->metadata, $other->all());

        return $this;
    }

    /**
     * Convert metadata to JSON
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->metadata, $options) ?: '';
    }

    /**
     * Convert metadata to array (alias for all())
     */
    public function toArray(): array
    {
        return $this->all();
    }
}
