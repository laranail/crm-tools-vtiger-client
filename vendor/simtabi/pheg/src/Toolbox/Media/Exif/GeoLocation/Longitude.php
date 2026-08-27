<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Media\Exif\GeoLocation;

use Simtabi\Pheg\Toolbox\Media\Exif\Traits\HasCoordinatesTrait;

class Longitude
{
    use HasCoordinatesTrait;

    /** @var array<string> */
    private const AVAILABLE_REF = ['W', 'E'];

    /** @var int */
    private const BOUNDARY      = 180;
}
