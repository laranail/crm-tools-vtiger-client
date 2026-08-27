<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Archiver;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Simtabi\Laranail\Toolkit\Modules\Archiver\Tar   tar()
 * @method static \Simtabi\Laranail\Toolkit\Modules\Archiver\TarGz tarGz()
 * @method static \Simtabi\Laranail\Toolkit\Modules\Archiver\Zip   zip()
 * @method static void                                             extract(string $pathToArchive, string $pathToDirectory)
 *
 * @see ArchiverServiceInterface
 */
class Archiver extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ArchiverServiceInterface::class;
    }
}
