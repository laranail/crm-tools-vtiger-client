<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

trait HasValidationRules
{
    /**
     * Custom validator rules to register at boot. Each entry adapts a Laravel
     * `ValidationRule`/invokable class into a `Validator::extend` closure.
     *
     * @var list<array{name: string, rule: class-string, message: ?string}>
     */
    public array $validationRules = [];

    /**
     * Register a custom validation rule by name, backed by a rule class with a
     * no-argument constructor and a `validate($attribute, $value, $fail)` method.
     *
     * @param class-string $ruleClass
     */
    public function hasValidationRule(string $name, string $ruleClass, ?string $message = null): static
    {
        $this->validationRules[] = [
            'name' => $name,
            'rule' => $ruleClass,
            'message' => $message,
        ];

        return $this;
    }

    /**
     * Register several validation rules at once, keyed by rule name. Each value
     * is either a `class-string` (no custom message) or a `[class-string, message]`
     * pair.
     *
     * @param array<string, class-string|array{0: class-string, 1?: ?string}> $rules
     */
    public function hasValidationRules(array $rules): static
    {
        foreach ($rules as $name => $rule) {
            is_array($rule)
                ? $this->hasValidationRule($name, $rule[0], $rule[1] ?? null)
                : $this->hasValidationRule($name, $rule);
        }

        return $this;
    }
}
