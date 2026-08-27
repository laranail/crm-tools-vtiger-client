<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Archiver;

final class ArchiveManager
{
    /** @var array<string, Extractor> */
    private array $extractors = [];

    public function __construct()
    {
        $this->addExtractor('zip', new Zip())
            ->addExtractor('tar', new Tar())
            ->addExtractor('tar.gz', new TarGz());
    }

    public function addExtractor(string $extension, Extractor $extractor): self
    {
        $this->extractors[strtolower($extension)] = $extractor;

        return $this;
    }

    public function getExtractor(string $extension): Extractor
    {
        return $this->extractors[strtolower($extension)]
            ?? throw ArchiveException::missingExtractor($extension);
    }

    /**
     * Extract an archive, choosing the extractor from the file extension.
     */
    public function extract(string $pathToArchive, string $pathToDirectory): void
    {
        $this->getExtractor($this->detectExtension($pathToArchive))
            ->extract($pathToArchive, $pathToDirectory);
    }

    private function detectExtension(string $path): string
    {
        $lower = strtolower($path);

        return match (true) {
            str_ends_with($lower, '.tar.gz'), str_ends_with($lower, '.tgz') => 'tar.gz',
            str_ends_with($lower, '.tar') => 'tar',
            str_ends_with($lower, '.zip') => 'zip',
            default => pathinfo($lower, PATHINFO_EXTENSION),
        };
    }
}
