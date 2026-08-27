<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\PhoneNumber;

use JsonSerializable;
use libphonenumber\geocoding\PhoneNumberOfflineGeocoder;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber as LibphoneNumberPhoneNumber;
use libphonenumber\PhoneNumberToCarrierMapper;
use libphonenumber\PhoneNumberToTimeZonesMapper;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\ShortNumberInfo;
use Simtabi\Pheg\Toolbox\PhoneNumber\Exceptions\PhoneNumberException;
use Simtabi\Pheg\Toolbox\PhoneNumber\Exceptions\PhoneNumberParseException;
use Simtabi\Pheg\Toolbox\PhoneNumber\Supports\NanpFormatter;
use Simtabi\Pheg\Toolbox\PhoneNumber\Supports\PhoneNumberFormat;
use Simtabi\Pheg\Toolbox\PhoneNumber\Supports\PhoneNumberType;
use Simtabi\Pheg\Toolbox\PhoneNumber\Traits\HasAsYouTypeFormatter;
use Simtabi\Pheg\Toolbox\PhoneNumber\Traits\HasPhoneNumberMatcher;
use Simtabi\Pheg\Toolbox\PhoneNumber\Traits\HasPhoneNumberToCarrierMapper;
use Simtabi\Pheg\Toolbox\PhoneNumber\Traits\HasPhoneNumberToTimeZonesMapper;
use Simtabi\Pheg\Toolbox\PhoneNumber\Traits\HasPhoneNumberUtil;
use Simtabi\Pheg\Toolbox\PhoneNumber\Traits\HasPhoneNumberOfflineGeocoder;
use Simtabi\Pheg\Toolbox\PhoneNumber\Traits\HasShortNumberInfo;
use Simtabi\Pheg\Toolbox\String\Str;

/**
 * A phone number.
 */
final class PhoneNumber implements JsonSerializable
{

    use HasAsYouTypeFormatter;
    use HasPhoneNumberMatcher;
    use HasPhoneNumberOfflineGeocoder;
    use HasPhoneNumberToCarrierMapper;
    use HasPhoneNumberToTimeZonesMapper;
    use HasPhoneNumberUtil;
    use HasShortNumberInfo;

    /**
     * The underlying HasPhoneNumberToTimeZonesMapper object from libphonenumber.
     */
    private static PhoneNumberToTimeZonesMapper $phoneNumberToTimeZonesMapper;

    /**
     * The underlying HasPhoneNumberOfflineGeocoder object from libphonenumber.
     */
    private static PhoneNumberOfflineGeocoder $phoneNumberOfflineGeocoder;

    /**
     * The underlying HasPhoneNumberToCarrierMapper object from libphonenumber.
     */
    private static PhoneNumberToCarrierMapper $phoneNumberToCarrierMapper;

    /**
     * The underlying PhoneNumberUtil object from libphonenumber.
     */
    private static PhoneNumberUtil $phoneNumberUtil;

    /**
     * The underlying HasShortNumberInfo object from libphonenumber.
     */
    private static ShortNumberInfo $shortNumberInfo;

    /**
     * The underlying PhoneNumber object from libphonenumber.
     */
    private static LibphoneNumberPhoneNumber $phoneNumberObj;

    /**
     * Private constructor. Use a factory method to obtain an instance.
     */
    private function __construct(LibphoneNumberPhoneNumber $phoneNumberObj)
    {
        self::$phoneNumberToTimeZonesMapper = PhoneNumberToTimeZonesMapper::getInstance();
        self::$phoneNumberOfflineGeocoder   = PhoneNumberOfflineGeocoder::getInstance();
        self::$phoneNumberToCarrierMapper   = PhoneNumberToCarrierMapper::getInstance();
        self::$phoneNumberUtil              = PhoneNumberUtil::getInstance();
        self::$shortNumberInfo              = ShortNumberInfo::getInstance();
        self::$phoneNumberObj               = $phoneNumberObj;
    }





    /**
     * Returns the country code of this PhoneNumber.
     *
     * The country code is a series of 1 to 3 digits, as defined per the E.164 recommendation.
     *
     * @return string
     */
    public function getCountryCode(): string
    {
        return (string) self::$phoneNumberObj->getCountryCode();
    }

    /**
     * Returns the geographical area code of this PhoneNumber.
     *
     * Notes:
     *
     *  - geographical area codes change over time, and this method honors those changes; therefore, it doesn't
     *    guarantee the stability of the result it produces;
     *  - most non-geographical numbers have no area codes, including numbers from non-geographical entities;
     *  - some geographical numbers have no area codes.
     *
     * If this number has no area code, an empty string is returned.
     *
     * @return string
     */
    public function getGeographicalAreaCode(): string
    {

        $nationalSignificantNumber = self::$phoneNumberUtil->getNationalSignificantNumber(self::$phoneNumberObj);
        $areaCodeLength            = self::$phoneNumberUtil->getLengthOfGeographicalAreaCode(self::$phoneNumberObj);

        return substr($nationalSignificantNumber, 0, $areaCodeLength);
    }

    /**
     * Returns the national number of this PhoneNumber.
     *
     * The national number is a series of digits.
     *
     * @return string
     */
    public function getNationalNumber(): string
    {
        return self::$phoneNumberObj->getNationalNumber();
    }








    /**
     * Returns a formatted string representation of this phone number.
     *
     * @param int $format
     *
     * @return string
     */
    public function format(int $format): string
    {
        return self::$phoneNumberUtil->format(self::$phoneNumberObj, $format);
    }

    public function isEqualTo(LibphoneNumberPhoneNumber $phoneNumber): bool
    {
        return self::$phoneNumberObj->equals($phoneNumber);
    }

    /**
     * Required by interface JsonSerializable.
     */
    public function jsonSerialize(): string
    {
        return (string) $this;
    }

    /**
     * Returns a text description for the given phone number, in the language provided. The description might consist of
     * the name of the country where the phone number is from, or the name of the geographical area the phone number is
     * from if more detailed information is available.
     *
     * If $userRegion is set, we also consider the region of the user. If the phone number is from the same region as
     * the user, only a lower-level description will be returned, if one exists. Otherwise, the phone number's region
     * will be returned, with optionally some more detailed information.
     *
     * For example, for a user from the region "US" (United States), we would show "Mountain View, CA" for a particular
     * number, omitting the United States from the description. For a user from the United Kingdom (region "GB"), for
     * the same number we may show "Mountain View, CA, United States" or even just "United States".
     *
     * If no description is found, this method returns null.
     *
     * @param string      $locale     The locale for which the description should be written.
     * @param string|null $userRegion The region code for a given user. This region will be omitted from the description
     *                                if the phone number comes from this region. It is a two-letter uppercase CLDR
     *                                region code.
     *
     * @return string|null
     */
    public function getDescription(string $locale, string|null $userRegion = null): string|null
    {
        $description = PhoneNumberOfflineGeocoder::getInstance()->getDescriptionForNumber(
            self::$phoneNumberObj,
            $locale,
            $userRegion
        );

        if ($description === '') {
            return null;
        }

        return $description;
    }

    /**
     * Returns a string representation of this phone number in international E164 format.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->format(PhoneNumberFormat::E164);
    }

    /**
     * This method masks parts of a phone number
     *
     * @param string|int $string         the number address to mask
     * @param int        $maskPercentile the percent of the number to mask
     * @param string     $maskChar       the character to use to mask with
     *
     * @return string $result
     */
    public function mask(string|int $string, int $maskPercentile = 60, string $maskChar = '*'): string
    {
        return (new Str())->maskString($string, $maskPercentile, $maskChar);
    }

    /**
     * @param LibphoneNumberPhoneNumber|string $number
     * @param string                           $defaultRegion
     *
     * @return LibphoneNumberPhoneNumber
     * @throws NumberParseException
     */
    protected function getNumberProto(LibphoneNumberPhoneNumber|string $number, string $defaultRegion = PhoneNumberFormat::DEFAULT_REGION): LibphoneNumberPhoneNumber
    {

        if ($number instanceof LibphoneNumberPhoneNumber) {
            return $number;
        }

        $region = !str_contains($number, '+') ? $defaultRegion : 'ZZ';

        return self::$phoneNumberUtil->parse($number, $region);
    }



    /**
     * Returns the name of the carrier for the supplied PhoneNumber object
     * within the $language supplied.
     *
     * @param string|int $number
     * @param string     $defaultRegion
     * @param string     $defaultLanguageCode
     *
     * @return string|bool
     * @throws PhoneNumberException
     * @see  PhoneNumberToCarrierMapper
     */
    public function getNameForNumber(string|int $number, string $defaultRegion = PhoneNumberFormat::DEFAULT_REGION, string $defaultLanguageCode = PhoneNumberFormat::DEFAULT_LANGUAGE): string|bool
    {
        try {
            $carrierMapper = PhoneNumberToCarrierMapper::getInstance();
            $phoneNumber   = self::$phoneNumberUtil->parse($number, $defaultRegion);

            return $carrierMapper->getNameForNumber($phoneNumber, $defaultLanguageCode);
        }catch (NumberParseException $exception) {
            throw new PhoneNumberException($exception->getMessage());
        }
    }

    /**
     * Returns a carrier name for the given phone number, in the language provided.
     * Returns the same as getNameForNumber() without checking whether it is a valid number
     * for carrier mapping.
     *
     * @param string|int $number
     * @param string     $defaultRegion
     * @param string     $defaultLanguageCode
     *
     * @return string|bool
     * @throws PhoneNumberException
     * @see  PhoneNumberToCarrierMapper
     */
    public function getNameForValidNumber(string|int $number, string $defaultRegion = PhoneNumberFormat::DEFAULT_REGION, string $defaultLanguageCode = PhoneNumberFormat::DEFAULT_LANGUAGE): string|bool
    {
        try {
            $carrierMapper = PhoneNumberToCarrierMapper::getInstance();
            $phoneNumber   = self::$phoneNumberUtil->parse($number, $defaultRegion);

            return $carrierMapper->getNameForValidNumber($phoneNumber, $defaultLanguageCode);
        }catch (NumberParseException $exception) {
            throw new PhoneNumberException($exception->getMessage());
        }
    }

    /**
     * Gets the name of the carrier for the given phone number only when it is 'safe' to display to
     * users. A carrier name is considered safe if the number is valid and for a region that doesn't
     * support. Returns the same as getNameForNumber(), but only if the number is safe for carrier mapping.
     * A number is only validate for carrier mapping if it’s a Mobile or Fixed line, and the country does not support Mobile Number Portability.
     *
     * @param string|int $number
     * @param string     $defaultRegion
     * @param string     $defaultLanguageCode
     *
     * @return string|bool
     * @throws PhoneNumberException
     * @see  PhoneNumberToCarrierMapper
     */
    public function getSafeDisplayName(string|int $number, string $defaultRegion = PhoneNumberFormat::DEFAULT_REGION, string $defaultLanguageCode = PhoneNumberFormat::DEFAULT_LANGUAGE): string|bool
    {
        try {
            $carrierMapper = PhoneNumberToCarrierMapper::getInstance();
            $phoneNumber   = self::$phoneNumberUtil->parse($number, $defaultRegion);

            return $carrierMapper->getSafeDisplayName($phoneNumber, $defaultLanguageCode);
        }catch (NumberParseException $exception) {
            throw new PhoneNumberException($exception->getMessage());
        }
    }

    public function nanp(string|int $number, bool $wildcards = false): NanpFormatter
    {
        return NanpFormatter::format($number, $wildcards);
    }

    public function unFormatInternationalPhoneNumber(string|int $number, bool $appendPlus = true): array|string|null
    {
        // Strip a Phone number of all non-alphanumeric characters
        $clean  = preg_replace('/(\W*)/', '', $number);
        $clean  = preg_replace('/\D+/',  '', $clean);

        return true === $appendPlus ? '+' . $clean : $clean;
    }

    public function getPhoneNumberInfo($number, $countryISOCode = PhoneNumberFormat::DEFAULT_REGION): array
    {

        // output variables
        $status = false;
        $errors = null;

        //initialization variables
        $geoLocation                = null;
        $countryCode                = null;
        $phoneNumberRegion          = null;
        $numberType                 = null;
        $carrierName                = null;
        $timezone                   = null;
        $isValidNumber              = false;
        $nationalNumber             = null;
        $isPossibleNumber           = false;
        $isPossibleNumberWithReason = false;
        $isValidNumberForRegion     = false;

        $formattedE164              = null;
        $formattedOriginal          = null;
        $formattedNational          = null;
        $formattedInternational     = null;
        $formattedRFC3966           = null;
        $formattedFromUS            = null;
        $formattedFromCH            = null;
        $formattedFromGB            = null;

        $connectsToEmergencyNumber  = false;
        $isEmergencyNumber          = false;
        $getSupportedRegions        = null;
        
        $_e = function (string $string): string
        {
            return $string;
        };

        try {

            // sanitize values
            $countryISOCode = trim($countryISOCode);
            $number         = trim($number);

            // validate country iso code
            if(!empty($countryISOCode)){
                if(!$this->validators->isCountry($countryISOCode)){
                    throw new PhoneNumberException('Invalid country ISO code');
                }
            }


            // validate number
            if((!$this->validators->isInteger($number)) && (!$this->validators->isString($number))){
                throw new PhoneNumberException($_e('INVALID_PHONE_NUMBER_FORMAT'));
            }

            //initialize classes
            $phoneNumberUtil            = PhoneNumberUtil::getInstance();
            $shortNumberUtil            = ShortNumberInfo::getInstance();
            $phoneNumberGeocoder        = PhoneNumberOfflineGeocoder::getInstance();

            $phoneNumber                = $phoneNumberUtil->parse($number, $countryISOCode, null, true);
            $isPossibleNumber           = $phoneNumberUtil->isPossibleNumber($phoneNumber);
            $isPossibleNumberWithReason = $phoneNumberUtil->isPossibleNumberWithReason($phoneNumber);
            $isValidNumber              = $phoneNumberUtil->isValidNumber($phoneNumber);
            $isValidNumberForRegion     = $phoneNumberUtil->isValidNumberForRegion($phoneNumber, $countryISOCode);
            $phoneNumberRegion          = $phoneNumberUtil->getRegionCodeForNumber($phoneNumber);
            $countryCode                = $phoneNumber->getCountryCode();
            $nationalNumber             = $phoneNumber->getNationalNumber();

            $formattedE164              = $phoneNumberUtil->format($phoneNumber, \libphonenumber\PhoneNumberFormat::E164);
            $formattedOriginal          = $phoneNumberUtil->formatInOriginalFormat($phoneNumber, $countryISOCode);
            $formattedNational          = $phoneNumberUtil->format($phoneNumber, PhoneNumberFormat::NATIONAL);
            $formattedInternational     = $phoneNumberUtil->format($phoneNumber, PhoneNumberFormat::INTERNATIONAL);
            $formattedRFC3966           = $phoneNumberUtil->format($phoneNumber, PhoneNumberFormat::RFC3966);
            $formattedFromUS            = $phoneNumberUtil->formatOutOfCountryCallingNumber($phoneNumber, "US");
            $formattedFromCH            = $phoneNumberUtil->formatOutOfCountryCallingNumber($phoneNumber, "CH");
            $formattedFromGB            = $phoneNumberUtil->formatOutOfCountryCallingNumber($phoneNumber, "GB");

            $connectsToEmergencyNumber  = $shortNumberUtil->connectsToEmergencyNumber($number, $countryISOCode);
            $getSupportedRegions        = $shortNumberUtil->getSupportedRegions();
            $isEmergencyNumber          = $shortNumberUtil->isEmergencyNumber($number, $countryISOCode);

            //get the number type
            $getNumberType              = $phoneNumberUtil->getNumberType($phoneNumber);
            $numberType                 = match ($getNumberType) {
                0       => $_e('FIXED_LINE_NUMBER'),
                1       => $_e('MOBILE_NUMBER'),
                2       => $_e('FIXED_LINE_NUMBER_OR_MOBILE_NUMBER'),
                3       => $_e('TOLL_FREE_NUMBER'),
                4       => $_e('PREMIUM_RATE_NUMBER'),
                5       => $_e('SHARED_COST_NUMBER'),
                6       => $_e('VOIP_SERVICE_NUMBER'),
                7       => $_e('PERSONAL_NUMBER'),
                8       => $_e('PAGER_NUMBER'),
                9       => $_e('UAN_NUMBER'),
                10      => $_e('UNKNOWN_NUMBER'),
                27      => $_e('EMERGENCY_NUMBER'),
                28      => $_e('VOICE_MAIL_NUMBER'),
                29      => $_e('SHORT_CODE_NUMBER'),
                30      => $_e('STANDARD_RATE_NUMBER'),
                default => $_e('UNKNOWN_NUMBER_TYPE'),
            };

            $numberType  = ucwords(strtolower($numberType));

            //get geo data information
            $geoLocation = $phoneNumberGeocoder->getDescriptionForNumber($phoneNumber, $phoneNumberRegion, $phoneNumberRegion);

            //get special number information and timezone info
            $carrierName = PhoneNumberToCarrierMapper::getInstance()->getNameForNumber($phoneNumber, $phoneNumberRegion);
            $timezone    = PhoneNumberToTimeZonesMapper::getInstance()->getTimeZonesForNumber($phoneNumber);
            $timezone    = is_string($timezone) ? implode(',', $timezone) : $timezone;

            //validate if carrier name is available
            if(empty($carrierName)){
                $carrierName = $_e('CARRIER_NAME_NOT_SET');
            }

            // set status
            $status = true;

        } catch (PhoneNumberException | NumberParseException $e) {
            $errors = $e->getMessage();
        }

        return [
            'status' => $status,
            'errors' => \Simtabi\Pheg\Toolbox\Helpers::filterArray($errors),
            'query'  => [
                'geoLocation'                => $geoLocation ?? null,
                'countryCode'                => $countryCode,
                'phoneNumberRegion'          => $phoneNumberRegion,
                'numberType'                 => $numberType,
                'carrierName'                => $carrierName,
                'timezone'                   => $timezone,
                'isValidNumber'              => $isValidNumber,
                'nationalNumber'             => $nationalNumber,
                'isPossibleNumber'           => $isPossibleNumber,
                'isPossibleNumberWithReason' => $isPossibleNumberWithReason,
                'isValidNumberForRegion'     => $isValidNumberForRegion,

                'formattedE164'              => $formattedE164,
                'formattedOriginal'          => $formattedOriginal,
                'formattedNational'          => $formattedNational,
                'formattedInternational'     => $formattedInternational,
                'formattedRFC3966'           => $formattedRFC3966,
                'formattedFromUS'            => $formattedFromUS,
                'formattedFromCH'            => $formattedFromCH,
                'formattedFromGB'            => $formattedFromGB,

                'connectsToEmergencyNumber'  => $connectsToEmergencyNumber,
                'isEmergencyNumber'          => $isEmergencyNumber,
                'supportedRegions'           => $getSupportedRegions,
            ],

        ];

    }

}
