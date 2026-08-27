<?php

namespace Simtabi\Pheg\Toolbox\PhoneNumber\Traits;

trait HasPhoneNumberToTimeZonesMapper
{

    public function getTimeZonesForNumber(): array
    {
        $usNumber = \libphonenumber\PhoneNumberUtil::getInstance()->parse("+1 650 253 0000", "US");

        return $timezoneMapper->getTimeZonesForNumber($usNumber);
    }

}