<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Archiver;

use PharData;
use Throwable;

class Tar extends Extractor
{
    public function extract(string $pathToArchive, string $pathToDirectory): void
    {
        try {
            $phar = new PharData($pathToArchive);
        } catch (Throwable) {
            throw ArchiveException::cannotOpen($pathToArchive);
        }

        $this->ensureDestination($pathToDirectory);
        $this->validatePharEntries($phar, $pathToDirectory);

        $phar->extractTo($pathToDirectory, null, true);
    }
}
