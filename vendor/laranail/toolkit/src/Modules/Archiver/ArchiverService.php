<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Archiver;

final class ArchiverService implements ArchiverServiceInterface
{
    public function tar(): Tar
    {
        return new Tar();
    }

    public function tarGz(): TarGz
    {
        return new TarGz();
    }

    public function zip(): Zip
    {
        return new Zip();
    }

    public function extract(string $pathToArchive, string $pathToDirectory): void
    {
        (new ArchiveManager())->extract($pathToArchive, $pathToDirectory);
    }
}
