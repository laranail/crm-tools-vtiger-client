<?php

namespace Simtabi\Pheg\Toolbox\PhoneNumber\Traits;

trait HasPhoneNumberMatcher
{

    public function matchPhoneNumber()
    {
        $phoneNumberUtil = \libphonenumber\PhoneNumberUtil::getInstance();

        $text = "Hi, can you ring me at 1430 on 0117 496 0123. Thanks!";

        $phoneNumberMatcher = $phoneNumberUtil->findNumbers($text, 'GB');

        foreach ($phoneNumberMatcher as $phoneNumberMatch) {
            var_dump($phoneNumberMatch->number());
        }
    }

}