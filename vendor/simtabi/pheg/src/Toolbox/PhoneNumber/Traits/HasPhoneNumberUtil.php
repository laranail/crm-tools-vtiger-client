<?php

namespace Simtabi\Pheg\Toolbox\PhoneNumber\Traits;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber as LibphoneNumberPhoneNumber;
use Simtabi\Pheg\Toolbox\PhoneNumber\Exceptions\PhoneNumberException;
use Simtabi\Pheg\Toolbox\PhoneNumber\Exceptions\PhoneNumberParseException;
use Simtabi\Pheg\Toolbox\PhoneNumber\PhoneNumber;
use Simtabi\Pheg\Toolbox\PhoneNumber\Supports\PhoneNumberFormat;
use Simtabi\Pheg\Toolbox\PhoneNumber\Supports\PhoneNumberType;

trait HasPhoneNumberUtil
{

    /**
     * Parses a string representation of a phone number.
     *
     * @param string|int  $phoneNumber The phone number to parse.
     * @param string|null $regionCode  The region code to assume, if the number is not in international format.
     *
     * @return PhoneNumber
     *
     * @throws PhoneNumberParseException
     */
    public static function parse(string|int $phoneNumber, string|null $regionCode = PhoneNumberFormat::DEFAULT_REGION): PhoneNumber
    {
        try {
            return new self(self::$phoneNumberUtil->parse($phoneNumber, $regionCode));
        } catch (NumberParseException $e) {
            throw PhoneNumberParseException::wrap($e);
        }
    }

    /**
     * Returns the region code of this PhoneNumber.
     *
     * The region code is an ISO 3166-1 alpha-2 country code.
     *
     * If the phone number does not map to a geographic region
     * (global networks, such as satellite phone numbers) this method returns null.
     *
     * @return string|null The region code, or null if the number does not map to a geographic region.
     */
    public function getRegionCode(): string|null
    {
        $regionCode = self::$phoneNumberUtil->getRegionCodeForNumber(self::$phoneNumberObj);

        if ($regionCode === '001') {
            return null;
        }

        return $regionCode;
    }

    /**
     * Returns the type of this phone number.
     *
     * @return PhoneNumberType::*
     */
    public function getNumberType(): int
    {
        return self::$phoneNumberUtil->getNumberType(self::$phoneNumberObj);
    }

    /**
     * @param LibphoneNumberPhoneNumber|string $phoneNumber
     * @param string|null                      $countryCode
     *
     * @return bool
     * @throws NumberParseException
     */
    public function canBeInternationallyDialled(LibphoneNumberPhoneNumber|string $phoneNumber, string|null $countryCode = PhoneNumberFormat::DEFAULT_REGION): bool
    {
        if (empty($countryCode)) {
            return self::$phoneNumberUtil->canBeInternationallyDialled(self::$phoneNumberObj);
        } else {
            return self::$phoneNumberUtil->canBeInternationallyDialled(self::$phoneNumberUtil->parse($phoneNumber, $countryCode));
        }
    }

    /**
     * Returns whether this phone number is a possible number.
     *
     * Note this provides a more lenient and faster check than `isValidNumber()`.
     *
     * @param string|int|null $phoneNumber
     * @param string|null     $countryCode
     *
     * @return bool
     * @throws NumberParseException
     */
    public function isPossibleNumber(string|int|null $phoneNumber = null, string|null $countryCode = PhoneNumberFormat::DEFAULT_REGION): bool
    {
        if (empty($countryCode)) {
            return self::$phoneNumberUtil->isPossibleNumber(self::$phoneNumberObj);
        } else {
            return self::$phoneNumberUtil->isPossibleNumber(self::$phoneNumberUtil->parse($phoneNumber, $countryCode));
        }
    }

    /**
     * Returns whether this phone number is a possible number for type with reason.
     *
     * @param string|int|null $phoneNumber
     * @param string|null     $numberType
     * @param string|null     $countryCode
     *
     * @return bool
     * @throws NumberParseException
     */
    public function isPossibleNumberForTypeWithReason(string|int|null $phoneNumber = null, string|null $numberType = PhoneNumberType::FIXED_LINE, string|null $countryCode = PhoneNumberFormat::DEFAULT_REGION): bool
    {
        if (empty($phoneNumber)) {
            return self::$phoneNumberUtil->isPossibleNumberForTypeWithReason(
                self::$phoneNumberObj,
                $numberType
            );
        } else {
            return self::$phoneNumberUtil->isPossibleNumberForTypeWithReason(
                self::$phoneNumberUtil->parse($phoneNumber, $countryCode),
                $numberType
            );
        }
    }

    /**
     * Returns whether this phone number matches a valid pattern.
     *
     * Note this doesn't verify the number is actually in use,
     * which is impossible to tell by just looking at a number itself.
     *
     * @param string|int|null $phoneNumber
     * @param string|null     $countryCode
     *
     * @return bool
     * @throws PhoneNumberException
     */
    public function isValidNumber(string|int|null $phoneNumber, string|null $countryCode = PhoneNumberFormat::DEFAULT_REGION): bool
    {
        try {
            if (!empty($countryCode)) {
                return self::$phoneNumberUtil->isValidNumber(self::$phoneNumberUtil->parse($phoneNumber, $countryCode));
            } else {
                return self::$phoneNumberUtil->isValidNumber(self::$phoneNumberObj);
            }
        } catch (NumberParseException $e) {
            throw new PhoneNumberException($e->getMessage());
        }
    }

    /**
     * Returns whether this phone number is valid for a given region.
     *
     * @param string|int|null $phoneNumber
     * @param string|null     $countryCode
     *
     * @return bool
     * @throws PhoneNumberException
     */
    public function isValidNumberForRegion(string|int|null $phoneNumber, string|null $countryCode = PhoneNumberFormat::DEFAULT_REGION): bool
    {
        try {
            if (!empty($countryCode)) {
                return self::$phoneNumberUtil->isValidNumberForRegion(self::$phoneNumberUtil->parse($phoneNumber, $countryCode), $countryCode);
            } else {
                return self::$phoneNumberUtil->isValidNumberForRegion(self::$phoneNumberObj, $countryCode);
            }
        } catch (NumberParseException $e) {
            throw new PhoneNumberException($e->getMessage());
        }
    }

    /**
     * @param LibphoneNumberPhoneNumber|string $number
     * @param string                           $countryCode
     *
     * @return string
     * @throws NumberParseException
     */
    public function e164(LibphoneNumberPhoneNumber|string $number, string $countryCode = PhoneNumberFormat::DEFAULT_REGION): string
    {
        return self::$phoneNumberUtil->format(
            $this->getNumberProto($number, $countryCode),
            PhoneNumberFormat::E164
        );
    }

    /**
     * @param LibphoneNumberPhoneNumber|string $number
     * @param string                           $countryCode
     *
     * @return string
     * @throws NumberParseException
     */
    public function international(LibphoneNumberPhoneNumber|string $number, string $countryCode = PhoneNumberFormat::DEFAULT_REGION): string
    {
        return self::$phoneNumberUtil->format(
            $this->getNumberProto($number, $countryCode),
            PhoneNumberFormat::INTERNATIONAL
        );
    }

    /**
     * @param LibphoneNumberPhoneNumber|string $number
     * @param string                           $countryCode
     *
     * @return string
     * @throws NumberParseException
     */
    public function national(LibphoneNumberPhoneNumber|string $number, string $countryCode = PhoneNumberFormat::DEFAULT_REGION): string
    {
        return self::$phoneNumberUtil->format(
            $this->getNumberProto($number, $countryCode),
            PhoneNumberFormat::NATIONAL
        );
    }

    /**
     * @param string|LibphoneNumberPhoneNumber $number
     * @param string                           $countryCode
     *
     * @return string
     * @throws NumberParseException
     */
    public function localized(LibphoneNumberPhoneNumber|string $number, string $countryCode = PhoneNumberFormat::DEFAULT_REGION): string
    {
        $proto = $this->getNumberProto($number, $countryCode);
        return self::$phoneNumberUtil->getRegionCodeForNumber($proto) == $countryCode ? self::national($proto): self::international($proto);
    }

    /**
     * @param LibphoneNumberPhoneNumber|string $number
     * @param string                           $countryCode
     *
     * @return string
     * @throws NumberParseException
     */
    public function rfc3966(LibphoneNumberPhoneNumber|string $number, string $countryCode = PhoneNumberFormat::DEFAULT_REGION): string
    {
        return self::$phoneNumberUtil->format(
            $this->getNumberProto($number, $countryCode),
            PhoneNumberFormat::RFC3966
        );
    }

    /**
     * Formats this phone number for out-of-country dialing purposes.
     *
     * @param string $regionCode The ISO 3166-1 alpha-2 country code
     *
     * @return string
     */
    public function formatForCallingFrom(string $regionCode): string
    {
        return self::$phoneNumberUtil->formatOutOfCountryCallingNumber(self::$phoneNumberObj, $regionCode);
    }

    /**
     * Formats this phone number for mobile dialing purposes.
     *
     * @param string|int|null $phoneNumber
     * @param string|null     $countryCode
     * @param bool            $withDefault
     *
     * @return string
     * @throws NumberParseException
     */
    public function formatNumberForMobileDialing(string|int $phoneNumber, string|null $countryCode = PhoneNumberFormat::DEFAULT_REGION, bool $withDefault = true): string
    {
        return self::$phoneNumberUtil->formatNumberForMobileDialing(
            self::$phoneNumberUtil->parse($phoneNumber, $countryCode),
            $countryCode,
            $withDefault
        );
    }

    /**
     * Formats a national phone number with career code
     *
     * @param string|int  $phoneNumber
     * @param string      $carrierCode
     * @param string|null $countryCode
     *
     * @return string
     * @throws NumberParseException
     */
    public function formatNationalNumberWithCarrierCode(string|int $phoneNumber, string $carrierCode, string|null $countryCode = PhoneNumberFormat::DEFAULT_REGION): string
    {
        return self::$phoneNumberUtil->formatNationalNumberWithCarrierCode(
            self::$phoneNumberUtil->parse($phoneNumber, $countryCode),
            $carrierCode
        );
    }

    /**
     * Formats a national phone number with career code
     *
     * @param string|int  $nationalPhoneNumber
     * @param string      $carrierCode
     * @param string|null $countryCode
     *
     * @return string
     */
    public function formatNationalNumberWithPreferredCarrierCode(string|int $nationalPhoneNumber, string $carrierCode, string|null $countryCode = PhoneNumberFormat::DEFAULT_REGION): string
    {
        $phoneNumber = new \libphonenumber\PhoneNumber();
        $phoneNumber->setCountryCode($countryCode)->setNationalNumber($nationalPhoneNumber);
        $phoneNumber->setPreferredDomesticCarrierCode($carrierCode);

        return self::$phoneNumberUtil->formatNationalNumberWithPreferredCarrierCode($phoneNumber, $carrierCode);
    }

    /**
     * @param string $regionCode The region code.
     *
     * @return PhoneNumber
     *
     * @throws PhoneNumberException If no example number is available for this region and type.
     */
    public function getExampleNumber(string $regionCode = PhoneNumberFormat::DEFAULT_REGION): static
    {
        $phoneNumber = self::$phoneNumberUtil->getExampleNumber($regionCode);

        if ($phoneNumber === null) {
            throw new PhoneNumberException('No example number is available for the given region and type.');
        }

        return new self($phoneNumber);
    }

    /**
     * @param string $regionCode      The region code.
     * @param int    $phoneNumberType The phone number type, defaults to a fixed line.
     *
     * @return PhoneNumber
     *
     * @throws PhoneNumberException If no example number is available for this region and type.
     */
    public function getExampleNumberForType(string $regionCode = PhoneNumberFormat::DEFAULT_REGION, int $phoneNumberType = PhoneNumberType::FIXED_LINE): static
    {
        $phoneNumber = self::$phoneNumberUtil->getExampleNumberForType($regionCode, $phoneNumberType);

        if ($phoneNumber === null) {
            throw new PhoneNumberException('No example number is available for the given region and type.');
        }

        return new self($phoneNumber);
    }

    /**
     * @param string $regionCode The region code.
     *
     * @return PhoneNumber
     *
     * @throws PhoneNumberException If no example number is available for this region and type.
     */
    public function getInvalidExampleNumber(string $regionCode = PhoneNumberFormat::DEFAULT_REGION): static
    {
        $phoneNumber = self::$phoneNumberUtil->getInvalidExampleNumber($regionCode);

        if ($phoneNumber === null) {
            throw new PhoneNumberException('No example number is available for the given region and type.');
        }

        return new self($phoneNumber);
    }

    /**
     * @param string $regionCode The region code.
     *
     * @return int
     */
    public function getCountryCodeForRegion(string $regionCode = PhoneNumberFormat::DEFAULT_REGION): int
    {
        return self::$phoneNumberUtil->getCountryCodeForRegion($regionCode);
    }

    /**
     * @param int $countryCallingCode The region code.
     *
     * @return array
     */
    public function getRegionCodesForCountryCode(int $countryCallingCode): array
    {
        return self::$phoneNumberUtil->getRegionCodesForCountryCode($countryCallingCode);
    }



}