<?php

namespace Simtabi\Pheg\Toolbox\PhoneNumber\Traits;

trait HasAsYouTypeFormatter
{

    public function getAsYouTypeFormatter()
    {
        $phoneNumberUtil = \libphonenumber\PhoneNumberUtil::getInstance();

        return $phoneNumberUtil->getAsYouTypeFormatter('GB')->inputDigit('0');
    }

}