<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Archiver;

use RuntimeException;

class ArchiveException extends RuntimeException
{
    public static function cannotOpen(string $path): self
    {
        return new self("Unable to open archive [{$path}].");
    }

    public static function unsafeEntry(string $entry): self
    {
        return new self("Refusing to extract unsafe archive entry [{$entry}] (path traversal or symlink).");
    }

    public static function tooLarge(): self
    {
        return new self('Refusing to extract archive: entry count or uncompressed size exceeds the configured limit.');
    }

    public static function missingExtractor(string $extension): self
    {
        return new self("There is no archive extractor registered for extension [{$extension}].");
    }
}
