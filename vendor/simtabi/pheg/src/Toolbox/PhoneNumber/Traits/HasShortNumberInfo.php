<?php

namespace Simtabi\Pheg\Toolbox\PhoneNumber\Traits;

use Simtabi\Pheg\Toolbox\PhoneNumber\Supports\PhoneNumberFormat;
use Simtabi\Pheg\Toolbox\PhoneNumber\Supports\ShortNumberCost;

trait HasShortNumberInfo
{

    public function getExampleShortNumber(string $regionCode = PhoneNumberFormat::DEFAULT_REGION): string
    {
        return self::$shortNumberInfo->getExampleShortNumber($regionCode);
    }

    public function getExampleShortNumberForCost(string $numberType = ShortNumberCost::TOLL_FREE, string $regionCode = PhoneNumberFormat::DEFAULT_REGION): string
    {
        return self::$shortNumberInfo->getExampleShortNumberForCost($regionCode, $numberType);
    }

    public function isEmergencyNumber(string $number, string $regionCode = PhoneNumberFormat::DEFAULT_REGION): bool
    {
        return self::$shortNumberInfo->isEmergencyNumber($regionCode, $number);
    }

    public function connectsToEmergencyNumber(string $number, string $regionCode = PhoneNumberFormat::DEFAULT_REGION): bool
    {
        return self::$shortNumberInfo->connectsToEmergencyNumber($regionCode, $number);
    }

    public function isPossibleShortNumber(string $nationalNumber, string $regionCode = PhoneNumberFormat::DEFAULT_REGION): bool
    {
        $phoneNumber = new \libphonenumber\PhoneNumber();
        $phoneNumber->setCountryCode($regionCode);
        $phoneNumber->setNationalNumber($nationalNumber);

        return self::$shortNumberInfo->isPossibleShortNumber($phoneNumber);
    }

    public function isPossibleShortNumberForRegion(string $nationalNumber, string $regionCode = PhoneNumberFormat::DEFAULT_REGION, string|null $countryCode = null): bool
    {
        if (!empty($countryCode)) {
            $phoneNumber = new \libphonenumber\PhoneNumber();
            $phoneNumber->setCountryCode($countryCode);
            $phoneNumber->setNationalNumber($nationalNumber);

            return self::$shortNumberInfo->isPossibleShortNumberForRegion($phoneNumber, $regionCode);
        }

        return self::$shortNumberInfo->isPossibleShortNumberForRegion($nationalNumber, $countryCode);
    }

    public function isValidShortNumber(string $nationalNumber, string|null $countryCode = null): bool
    {
        $phoneNumber = new \libphonenumber\PhoneNumber();
        $phoneNumber->setCountryCode($countryCode);
        $phoneNumber->setNationalNumber($nationalNumber);

        return self::$shortNumberInfo->isValidShortNumber($phoneNumber);
    }

    public function isValidShortNumberForRegion(string $nationalNumber, string $countryCode, string $regionCode = PhoneNumberFormat::DEFAULT_REGION): bool
    {
        if (!empty($countryCode)) {
            $phoneNumber = new \libphonenumber\PhoneNumber();
            $phoneNumber->setCountryCode($countryCode);
            $phoneNumber->setNationalNumber($nationalNumber);

            return self::$shortNumberInfo->isValidShortNumberForRegion($phoneNumber, $regionCode);
        }

        return self::$shortNumberInfo->isValidShortNumberForRegion($nationalNumber, $regionCode);
    }

    public function isCarrierSpecific(string $nationalNumber, string $countryCode): bool
    {
        $phoneNumber = new \libphonenumber\PhoneNumber();
        $phoneNumber->setCountryCode($countryCode);
        $phoneNumber->setNationalNumber($nationalNumber);

        return self::$shortNumberInfo->isCarrierSpecific($phoneNumber, $countryCode);
    }

    public function isCarrierSpecificForRegion(string $nationalNumber, string $countryCode, string $regionCode): bool
    {
        $phoneNumber = new \libphonenumber\PhoneNumber();
        $phoneNumber->setCountryCode($countryCode);
        $phoneNumber->setNationalNumber($nationalNumber);

        return self::$shortNumberInfo->isCarrierSpecificForRegion($phoneNumber, $regionCode);
    }

    public function getExpectedCost(string $nationalNumber, string $countryCode, string $regionCode): int
    {
        $phoneNumber = new \libphonenumber\PhoneNumber();
        $phoneNumber->setCountryCode($countryCode);
        $phoneNumber->setNationalNumber($nationalNumber);

        return self::$shortNumberInfo->getExpectedCost($phoneNumber, $regionCode);
    }

    public function getExpectedCostForRegion(string $nationalNumber, string $countryCode, string $regionCode): int
    {
        $phoneNumber = new \libphonenumber\PhoneNumber();
        $phoneNumber->setCountryCode($countryCode);
        $phoneNumber->setNationalNumber($nationalNumber);

        return self::$shortNumberInfo->getExpectedCostForRegion($phoneNumber, $regionCode);
    }

}