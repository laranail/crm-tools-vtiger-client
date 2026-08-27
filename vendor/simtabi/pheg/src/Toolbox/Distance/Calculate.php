<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Distance;

use Simtabi\Pheg\Toolbox\Distance\Entities\Calculator;
use Simtabi\Pheg\Toolbox\Distance\Entities\Distance;
use Simtabi\Pheg\Toolbox\Distance\Entities\LatLong;

final class Calculate
{

    public function __construct()
    {
    }

    public function calculator($pointA = null, $pointB = null): Calculator
    {
        return new Calculator($pointA, $pointB);
    }

    public function distance(): Distance
    {
        return new Distance();
    }

    public function latLong($lat, $lng): LatLong
    {
        return new LatLong($lat, $lng);
    }

}