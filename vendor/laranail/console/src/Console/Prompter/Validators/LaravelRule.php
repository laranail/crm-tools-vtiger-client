<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Prompter\Validators;

use Illuminate\Support\Facades\Validator;

/**
 * Bridges Illuminate validation rules into a Prompter validator, so prompt
 * validation can read like a form-request rule set:
 *
 *   $prompter->text('Email', validate: new LaravelRule(['required', 'email']));
 *
 * Returns the first Laravel validation message (or a fluent `->errorMessage()`
 * override), or null when the value passes.
 */
final class LaravelRule extends AbstractValidator
{
    /**
     * @param array<int|string, mixed>|string $rules
     * @param array<string, string> $messages
     */
    public function __construct(
        private readonly array|string $rules,
        private readonly array $messages = [],
    ) {
        parent::__construct();
    }

    public function validate(mixed $value): ?string
    {
        $validator = Validator::make(
            ['value' => $value],
            ['value' => $this->rules],
            $this->normaliseMessages(),
        );

        if ($validator->passes()) {
            return null;
        }

        return $this->customMessageOrNull() ?? (string) $validator->errors()->first('value');
    }

    /**
     * @return array<string, string>
     */
    private function normaliseMessages(): array
    {
        $normalised = [];

        foreach ($this->messages as $rule => $message) {
            $normalised["value.{$rule}"] = $message;
        }

        return $normalised;
    }
}
