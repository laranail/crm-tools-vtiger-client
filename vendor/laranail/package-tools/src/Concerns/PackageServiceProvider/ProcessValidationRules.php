<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\PackageServiceProvider;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;

trait ProcessValidationRules
{
    /**
     * Register the package's declared validation rules, adapting each Laravel
     * `ValidationRule` class into a `Validator::extend` closure.
     */
    protected function bootPackageValidationRules(): self
    {
        foreach ($this->package->validationRules as $rule) {
            $ruleClass = $rule['rule'];
            $message = $rule['message'];

            Validator::extend($rule['name'], static function (string $attribute, $value) use ($ruleClass): bool {
                $instance = new $ruleClass;

                if (! $instance instanceof ValidationRule) {
                    return true;
                }

                // Run the rule object through Laravel's own validator so the
                // ValidationRule::validate() contract is honoured natively.
                return validator([$attribute => $value], [$attribute => [$instance]])->passes();
            }, $message);

            if ($message !== null) {
                Validator::replacer(
                    $rule['name'],
                    static fn ($msg, $attribute): string => str_replace(':attribute', $attribute, $msg),
                );
            }
        }

        return $this;
    }
}
