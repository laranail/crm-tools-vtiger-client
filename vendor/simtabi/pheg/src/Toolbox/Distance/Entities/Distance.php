<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Distance\Entities;

use InvalidArgumentException;

class Distance
{

    /**
     * Conversion from Kilometers to Miles.
     */
    const KILOMETERS_IN_MILES           = 0.621371192;

    /**
     * Conversion from Kilometers to Nautical miles.
     */
    const KILOMETERS_INL_NAUTICAL_MILES = 0.539956803;

    /**
     * The distance in kilometres or miles
     * @var double|int
     */
    private $distance;

    /**
     * Class constructor
     */
    public function __construct()
    {

    }

    /**
     * @param float|int $distance
     */
    public function setDistance($distance = 0): self
    {

        $this->validateDistance($distance);

        $this->distance = $distance;

        return $this;
    }

    /**
     * @return float|int
     */
    public function getDistance(): mixed
    {
        return $this->distance;
    }

    /**
     * Validates the distance constructor value.
     * @param mixed $distance
     * @throws InvalidArgumentException
     * @return void
     */
    private function validateDistance($distance)
    {
        if (!is_numeric($distance)) {
            throw new InvalidArgumentException('The distance value must be of a valid type.');
        }
        if (!$distance > 0) {
            throw new InvalidArgumentException('The distance must be greater than zero!');
        }
    }

    /**
     * Distance as kilometres
     * @return double
     */
    public function asKilometres()
    {
        return $this->distance;
    }

    /**
     * Distance as miles
     * @return double
     */
    public function asMiles()
    {
        return $this->distance * self::KILOMETERS_IN_MILES;
    }

    /**
     * Distance as nautical miles
     * @return double
     */
    public function asNauticalMiles()
    {
        return $this->distance * self::KILOMETERS_INL_NAUTICAL_MILES;
    }

    /**
     * Default __toString() method, defaults to returning the distance as kilometres.
     * @return string
     */
    public function __toString()
    {
        return (string) $this->asKilometres();
    }
}
