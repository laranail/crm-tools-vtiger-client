<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Media\Exif\GeoLocation;

use Simtabi\Pheg\Toolbox\Media\Exif\Traits\HasCoordinatesTrait;

class Latitude
{
    use HasCoordinatesTrait;

    /** @var array<string> */
    private const AVAILABLE_REF = ['N', 'S'];

    /** @var int */
    private const BOUNDARY      = 90;
}
