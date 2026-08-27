<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Prompter\Enums;

enum ContextType: string
{
    case NOTE = 'note';
    case ERROR = 'error';
    case WARNING = 'warning';
    case ALERT = 'alert';
    case INFO = 'info';
    case INTRO = 'intro';
    case OUTRO = 'outro';
}
