<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Archiver;

use PharData;
use RecursiveIteratorIterator;
use SplFileInfo;

abstract class Extractor
{
    use GuardsArchivePaths;

    /** Maximum number of entries permitted in an archive (zip-bomb guard). */
    protected int $maxEntries = 10_000;

    /** Maximum total uncompressed size permitted, in bytes (zip-bomb guard). */
    protected int $maxTotalBytes = 1_073_741_824; // 1 GiB

    abstract public function extract(string $pathToArchive, string $pathToDirectory): void;

    public function setLimits(int $maxEntries, int $maxTotalBytes): static
    {
        $this->maxEntries = $maxEntries;
        $this->maxTotalBytes = $maxTotalBytes;

        return $this;
    }

    protected function assertWithinLimits(int $entryCount, int $totalBytes): void
    {
        if ($entryCount > $this->maxEntries || $totalBytes > $this->maxTotalBytes) {
            throw ArchiveException::tooLarge();
        }
    }

    protected function ensureDestination(string $directory): void
    {
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new ArchiveException("Unable to create destination directory [{$directory}].");
        }
    }

    /**
     * Validate every entry of a (possibly compressed) tar before extraction:
     * reject path traversal, symlinks, and bomb-sized archives.
     */
    protected function validatePharEntries(PharData $phar, string $destination): void
    {
        $iterator = new RecursiveIteratorIterator($phar);
        $count = 0;
        $total = 0;

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo) {
                continue;
            }

            $relative = $iterator->getSubPathName();

            $this->assertWithinDestination($destination, $relative);

            if ($file->isLink()) {
                throw ArchiveException::unsafeEntry($relative);
            }

            $total += $file->getSize();
            $this->assertWithinLimits(++$count, $total);
        }
    }
}
