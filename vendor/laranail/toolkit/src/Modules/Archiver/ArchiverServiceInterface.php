<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Archiver;

interface ArchiverServiceInterface
{
    public function tar(): Tar;

    public function tarGz(): TarGz;

    public function zip(): Zip;

    /**
     * Extract an archive into a directory, selecting the extractor from the
     * file extension. Safe against path traversal, symlinks and zip bombs.
     */
    public function extract(string $pathToArchive, string $pathToDirectory): void;
}
