<?php

declare(strict_types=1);

/*
 * This file is part of the PHP Humanizer Library.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Simtabi\Pheg\Toolbox\Humanizer\Number\Ordinal;

interface StrategyInterface
{
    public function isPrefix() : bool;

    /**
     * @param float|int $number
     *
     * @return string
     */
    public function ordinalIndicator($number) : string;
}
