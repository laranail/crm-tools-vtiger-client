<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Prompter\Validators;

/**
 * Class RadioFieldValidator
 *
 * Validates radio fields.
 */
final class RadioFieldValidator extends ChoiceFieldValidator
{
    /**
     * @param list<mixed> $options
     */
    public function __construct(array $options)
    {
        parent::__construct('radio', $options);
    }
}
