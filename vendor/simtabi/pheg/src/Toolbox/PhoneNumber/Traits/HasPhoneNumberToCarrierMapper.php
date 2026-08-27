<?php

namespace Simtabi\Pheg\Toolbox\PhoneNumber\Traits;

trait HasPhoneNumberToCarrierMapper
{

    public function getNameForNumber()
    {
        $chNumber = \libphonenumber\PhoneNumberUtil::getInstance()->parse("798765432", "CH");

        return $carrierMapper->getNameForNumber($chNumber, 'en');
    }

    public function getNameForValidNumber()
    {

    }

    public function getSafeDisplayName()
    {

    }

}