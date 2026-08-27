<?php

declare(strict_types=1);

/*
 * This file is part of the PHP Humanizer Library.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Simtabi\Pheg\Toolbox\Humanizer;

use Simtabi\Pheg\Toolbox\Humanizer\Collection\Formatter;
use Simtabi\Pheg\Toolbox\Humanizer\Collection\Oxford;
use Simtabi\Pheg\Toolbox\Humanizer\Translator\Builder;

final class CollectionHumanizer
{
    /**
     * @param array<string> $collection
     */
    public static function oxford(array $collection, int $limit = null, string $locale = 'en') : string
    {
        $oxford = new Oxford(
            new Formatter(Builder::build($locale))
        );

        return $oxford->format($collection, $limit);
    }
}
