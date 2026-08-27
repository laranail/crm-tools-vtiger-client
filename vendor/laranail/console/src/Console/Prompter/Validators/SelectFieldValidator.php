<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Prompter\Validators;

/**
 * Class SelectFieldValidator
 *
 * Validates select fields.
 */
final class SelectFieldValidator extends ChoiceFieldValidator
{
    /**
     * @param list<mixed> $options
     */
    public function __construct(array $options)
    {
        parent::__construct('select', $options);
    }
}
