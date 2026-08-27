<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Colors\Contracts;

interface PaletteInterface
{

    /**
     * @return array
     */
    public static function getColors();

    /**
     * @return void
     */
    public static function setColors(array $colors);
}








