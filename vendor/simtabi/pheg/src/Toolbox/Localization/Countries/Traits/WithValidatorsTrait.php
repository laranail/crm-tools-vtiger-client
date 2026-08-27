<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Localization\Countries\Traits;

trait WithValidatorsTrait
{

    public function isValidISO2CountryCode(): bool
    {
        $code = $this->countryCode;
        return !empty($code) && isset($this->getCountryIso2ToIso3()[$code]);
    }

    public function isValidISO3CountryCode(): bool
    {
        $code = $this->countryCode;
        return !empty($code) && isset($this->getCountryIso3ToIso2()[$code]) === true;
    }

    public function isValidCountryCode(): bool
    {
        if ($this->isValidISO3CountryCode() || $this->isValidISO2CountryCode()) {
            return true;
        }
        return false;
    }

}