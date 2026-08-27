<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox;

use PHLAK\StrGen\Generator;
use PHLAK\StrGen\CharSet;

final class Password
{

    public function __construct() {}

    public function generate($length = 10)
    {
        return (new Generator())->charset([CharSet::MIXED_ALPHA, CharSet::NUMERIC])->length($length)->generate();
    }

}