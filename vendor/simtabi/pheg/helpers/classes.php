<?php declare(strict_types=1);

use Simtabi\Pheg\Pheg;

if (!function_exists('pheg')) {
    function pheg()
    {
        return Pheg::getInstance();
    }
}