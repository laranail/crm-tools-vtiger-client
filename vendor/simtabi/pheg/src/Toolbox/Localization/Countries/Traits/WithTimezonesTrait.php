<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Localization\Countries\Traits;

use DateTimeZone;

trait WithTimezonesTrait
{

    /**
     * Get the timezones.
     *
     * @return array|null
     */
    public function getTimezones()
    {
        $code = $this->isValidISO3CountryCode() ? $this->getCountryIsoReversed() : $this->countryCode;

        return DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $code);
    }

}