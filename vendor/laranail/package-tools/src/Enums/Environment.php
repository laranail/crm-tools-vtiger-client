<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Enums;

/**
 * the standard laravel environment names as typed cases. custom environment
 * names remain legal anywhere these are accepted — pass the raw string.
 */
enum Environment: string
{
    case Production = 'production';
    case Staging = 'staging';
    case Local = 'local';
    case Testing = 'testing';
}
