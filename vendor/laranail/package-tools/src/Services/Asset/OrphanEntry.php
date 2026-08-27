<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Asset;

use Illuminate\Contracts\Support\Arrayable;

/**
 * One published path that nothing publishes any more.
 *
 * `attributedTag` is a best guess — the tag whose destination the path sits
 * under — and is null when nothing claims that part of the tree. It is for
 * telling an operator where a file probably came from, never for deciding
 * whether to delete it.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class OrphanEntry implements Arrayable
{
    public function __construct(
        public string $path,
        public string $relativePath,
        public bool $isDirectory,
        public int $bytes = 0,
        public ?string $attributedTag = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'relative_path' => $this->relativePath,
            'is_directory' => $this->isDirectory,
            'bytes' => $this->bytes,
            'attributed_tag' => $this->attributedTag,
        ];
    }
}
