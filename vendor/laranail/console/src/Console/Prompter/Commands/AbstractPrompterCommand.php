<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Prompter\Commands;

use Illuminate\Console\Command;
use Simtabi\Laranail\Console\Prompter\Helpers\Helpers;

abstract class AbstractPrompterCommand extends Command
{
    protected function sanitizeInput(?string $input, ?string $default = ''): ?string
    {
        return Helpers::sanitizeInput($input, $default);
    }
}
