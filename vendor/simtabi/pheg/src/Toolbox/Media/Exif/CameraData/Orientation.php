<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Media\Exif\CameraData;

use Simtabi\Pheg\Toolbox\Media\Exif\Traits\HasEnumerableIntTrait;

class Orientation
{
    use HasEnumerableIntTrait;

    /** @var array<string> */
    private $list = [
        1 => 'Standard',
        2 => 'Mirrored',
        3 => '180 degrees',
        4 => '180 degrees, mirrored',
        5 => '90 degrees',
        6 => '90 degrees, mirrored',
        7 => '270 degrees',
        8 => '270 degrees, mirrored',
    ];
}
