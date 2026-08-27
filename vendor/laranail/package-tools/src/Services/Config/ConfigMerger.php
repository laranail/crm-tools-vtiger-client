<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Config;

/**
 * Strategies for merging configuration arrays.
 */
class ConfigMerger
{
    /**
     * Recursively merge two configuration arrays, with later values
     * taking precedence.
     *
     * @param array<int|string, mixed> $base Base configuration array
     * @param array<int|string, mixed> $merge Configuration array to merge in
     * @return array<int|string, mixed> Merged configuration
     */
    public function deepMerge(array $base, array $merge): array
    {
        foreach ($merge as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = $this->deepMerge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    /**
     * Replace strategy - complete replacement
     *
     * @param array<int|string, mixed> $base Base configuration (ignored)
     * @param array<int|string, mixed> $merge Configuration to use
     * @return array<int|string, mixed> Replacement configuration
     */
    public function replaceStrategy(array $base, array $merge): array
    {
        return $merge;
    }

    /**
     * Append strategy - append arrays, replace scalars
     *
     * @param array<int|string, mixed> $base Base configuration
     * @param array<int|string, mixed> $merge Configuration to merge
     * @return array<int|string, mixed> Merged configuration
     */
    public function appendStrategy(array $base, array $merge): array
    {
        foreach ($merge as $key => $value) {
            if (isset($base[$key])) {
                $base[$key] = is_array($base[$key]) && is_array($value) ? array_merge($base[$key], $value) : $value;
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    /**
     * Merge using specified strategy
     *
     * @param array<int|string, mixed> $base Base configuration
     * @param array<int|string, mixed> $merge Configuration to merge
     * @param string $strategy Strategy name (deep, replace, append)
     * @return array<int|string, mixed> Merged configuration
     */
    public function mergeWithStrategy(array $base, array $merge, string $strategy = 'deep'): array
    {
        return match ($strategy) {
            'replace' => $this->replaceStrategy($base, $merge),
            'append' => $this->appendStrategy($base, $merge),
            default => $this->deepMerge($base, $merge),
        };
    }
}
