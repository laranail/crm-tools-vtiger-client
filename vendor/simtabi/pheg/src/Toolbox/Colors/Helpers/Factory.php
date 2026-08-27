<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Colors\Helpers;

use Exception;
use Simtabi\Pheg\Toolbox\Colors\Types\Hex;
use Simtabi\Pheg\Toolbox\Colors\Types\Hexa;
use Simtabi\Pheg\Toolbox\Colors\Types\Rgb;
use Simtabi\Pheg\Toolbox\Colors\Types\Rgba;
use Simtabi\Pheg\Toolbox\Colors\Types\Hsla;

class Factory
{
    /**
     * @throws Exception
     * @throws Exception
     */
    public static function init($color)
    {
        $color = str_replace(' ', '', $color);

        // Definitive types
        if (preg_match('/^(?P<type>(rgba?|hsla?|hsv))/i', $color, $match)) {
            $class = ucfirst(strtolower($match['type']));
            $class = 'Simtabi\\Pheg\\Toolbox\\Color\\Types' . $class;
            return new $class($color);
        }

        // Best guess
        if (preg_match('/^#?[a-f0-9]{8}$/i', $color)) {
            return new Hexa($color);
        }
        if (preg_match('/^#?[a-f0-9]{3}([a-f0-9]{3})?$/i', $color)) {
            return new Hex($color);
        }
        if (preg_match('/^[a-z]+$/i', $color)) {
            return new Hex($color);
        }
        if (preg_match('/^\d{1,3},\d{1,3},\d{1,3}$/', $color)) {
            return new Rgb($color);
        }
        if (preg_match('/^\d{1,3},\d{1,3},\d{1,3},[0-9\.]+$/', $color)) {
            return new Rgba($color);
        }
        if (preg_match('/^\d{1,3},\d{1,3}%,\d{1,3}%,[0-9\.]+$/', $color)) {
            return new Hsla($color);
        }
        // Cannot determine between hsv and hsl
        throw new Exception("Cannot determine color type of '{$color}'");
    }
}
