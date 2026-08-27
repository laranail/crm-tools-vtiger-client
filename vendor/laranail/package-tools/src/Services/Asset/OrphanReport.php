<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Asset;

use Illuminate\Contracts\Support\Arrayable;

/**
 * What a scan found, and what it could not see.
 *
 * `truncated` and `rootsScanned` are here so a report can never quietly
 * understate itself. A scan that hit its depth ceiling and reported "3 orphans"
 * reads exactly like a clean one unless it says so.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class OrphanReport implements Arrayable
{
    /**
     * @param list<OrphanEntry> $entries
     * @param list<string> $rootsScanned
     */
    public function __construct(
        public array $entries = [],
        public array $rootsScanned = [],
        public bool $truncated = false,
        public int $fileCount = 0,
        public int $bytes = 0,
    ) {}

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    public function count(): int
    {
        return count($this->entries);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'entries' => array_map(static fn (OrphanEntry $e): array => $e->toArray(), $this->entries),
            'roots_scanned' => $this->rootsScanned,
            'truncated' => $this->truncated,
            'count' => $this->count(),
            'file_count' => $this->fileCount,
            'bytes' => $this->bytes,
        ];
    }
}
