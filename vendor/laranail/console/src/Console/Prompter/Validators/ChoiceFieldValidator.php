<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Prompter\Validators;

/**
 * Shared base for "value must be one of a fixed set of options" validators
 * (radio, select, …). Subclasses supply only the message key.
 *
 * @internal Shared implementation of the choice validators; not a public extension point.
 */
abstract class ChoiceFieldValidator extends AbstractValidator
{
    /**
     * @param list<mixed> $options
     */
    public function __construct(string $messageKey, protected array $options)
    {
        parent::__construct($messageKey);
    }

    public function validate(mixed $value): ?string
    {
        return in_array($value, $this->options, true) ? null : $this->resolvedMessage();
    }
}
