<?php

namespace Simtabi\Pheg\Toolbox\PhoneNumber\Traits;

use libphonenumber\NumberParseException;
use Simtabi\Pheg\Toolbox\PhoneNumber\Supports\PhoneNumberFormat;

trait HasPhoneNumberOfflineGeocoder
{

    public function getDescriptionForNumber(string|int $phoneNumber, string $locale = PhoneNumberFormat::DEFAULT_LANGUAGE, string|null $regionCode = PhoneNumberFormat::DEFAULT_REGION): string
    {
        $parse = self::$phoneNumberUtil->parse($phoneNumber, $regionCode);

        if (!empty($regionCode)) {
            return self::$phoneNumberOfflineGeocoder->getDescriptionForNumber($parse, $locale, $regionCode);
        }

        return self::$phoneNumberOfflineGeocoder->getDescriptionForNumber($parse, $locale);
    }

    /**
     * @todo fix this
     *
     * @param string|int  $phoneNumber
     * @param string      $locale
     * @param string|null $regionCode
     *
     * @return string
     * @throws NumberParseException
     */
    public function getDescriptionForValidNumber(string|int $phoneNumber, string $locale = PhoneNumberFormat::DEFAULT_LANGUAGE, string|null $regionCode = PhoneNumberFormat::DEFAULT_REGION): string
    {
        $parse = self::$phoneNumberUtil->parse($phoneNumber, $regionCode);

        if (!empty($regionCode)) {
            return self::$phoneNumberOfflineGeocoder->getDescriptionForValidNumber($parse, $locale, $regionCode);
        }

        return self::$phoneNumberOfflineGeocoder->getDescriptionForValidNumber($parse, $locale);
    }

}