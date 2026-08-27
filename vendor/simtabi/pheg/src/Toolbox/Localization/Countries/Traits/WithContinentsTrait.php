<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Localization\Countries\Traits;

use DateTimeZone;

trait WithContinentsTrait
{

    public function getAllContinents(): array
    {
        return $this->getData('continents');
    }

    public function getContinent2Countries($continent): string|false
    {

    }

    public function getCountry2Continent($countryCode): string|false
    {

    }

}