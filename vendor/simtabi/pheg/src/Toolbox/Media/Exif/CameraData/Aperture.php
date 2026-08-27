<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Media\Exif\CameraData;

use Simtabi\Pheg\Toolbox\Media\Exif\Traits\HasExifRationalTrait;

class Aperture
{
    use HasExifRationalTrait;

    public function __toString(): string
    {
        return 'f/' . (string)$this->floatValue;
    }
}
