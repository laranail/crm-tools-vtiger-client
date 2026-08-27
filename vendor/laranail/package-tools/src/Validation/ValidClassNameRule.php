<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Validation;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validates that a string is a usable PHP class name: starts with a
 * letter or underscore, contains only letters, numbers, and underscores,
 * and is not a reserved keyword.
 */
class ValidClassNameRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param Closure(string, string|null=):PotentiallyTranslatedString $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail("The {$attribute} must be a string.");

            return;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            $fail("The {$attribute} cannot be empty.");

            return;
        }

        if (! preg_match('/^[a-zA-Z_]/', $trimmed)) {
            $fail("The {$attribute} must start with a letter or underscore.");

            return;
        }

        if (! preg_match('/^[a-zA-Z_]\w*$/', $trimmed)) {
            $fail("The {$attribute} contains invalid characters. Only letters, numbers, and underscores are allowed.");

            return;
        }

        if ($this->isReservedKeyword($trimmed)) {
            $fail("The {$attribute} '{$trimmed}' is a reserved PHP keyword and cannot be used as a class name.");

            return;
        }
    }

    /**
     * Check if the given value is a reserved PHP keyword
     */
    protected function isReservedKeyword(string $value): bool
    {
        $reserved = [
            // PHP keywords
            'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch',
            'class', 'clone', 'const', 'continue', 'declare', 'default', 'die', 'do',
            'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach',
            'endif', 'endswitch', 'endwhile', 'eval', 'exit', 'extends', 'final',
            'finally', 'for', 'foreach', 'function', 'global', 'goto', 'if',
            'implements', 'include', 'include_once', 'instanceof', 'insteadof',
            'interface', 'isset', 'list', 'namespace', 'new', 'or', 'print', 'private',
            'protected', 'public', 'require', 'require_once', 'return', 'static',
            'switch', 'throw', 'trait', 'try', 'unset', 'use', 'var', 'while', 'xor',
            'yield',
            // PHP type declarations
            'int', 'float', 'bool', 'string', 'true', 'false', 'null', 'void',
            'iterable', 'object', 'mixed', 'never', 'enum', 'readonly',
            // PHP 8.1+ keywords
            'match', 'fn',
        ];

        return in_array(strtolower($value), $reserved, true);
    }
}
