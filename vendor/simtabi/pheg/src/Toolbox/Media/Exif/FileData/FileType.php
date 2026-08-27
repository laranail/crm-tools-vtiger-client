<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Media\Exif\FileData;

use Simtabi\Pheg\Toolbox\Media\Exif\Traits\HasEnumerableIntTrait;

class FileType
{

    use HasEnumerableIntTrait;

    /**
     * See https://github.com/php/php-src/blob/master/ext/standard/php_image.h#L31-L55
     * @var array<string>
     */
    private $list = [
        0 => '',
        1 => 'GIF',
        2 => 'JPEG',
        3 => 'PNG',
        4 => 'SWF',
        5 => 'PSD',
        6 => 'BMP',
        7 => 'TIFF (Intel)',
        8 => 'TIFF (Motorola)',
        9 => 'JPC/JPEG2000',
        10 => 'JP2',
        11 => 'JPX',
        12 => 'JB2',
        13 => 'SWC',
        14 => 'IFF',
        15 => 'WBMP',
        16 => 'XBM',
        17 => 'ICO',
        18 => 'WEBP',
    ];
}
