<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Component;

use ReflectionClass;
use ReflectionException;
use Simtabi\Laranail\Package\Tools\Contracts\ValidatorInterface;

/**
 * Validates component classes and configurations.
 */
class ComponentValidator implements ValidatorInterface
{
    /**
     * {@inheritDoc}
     */
    public function validate(mixed $input): array
    {
        $errors = [];

        if (is_string($input)) {
            // Validate class name
            $errors = array_merge($errors, $this->validateClassName($input));
        } elseif (is_array($input)) {
            // Validate component array
            $errors = array_merge($errors, $this->validateComponentArray($input));
        } else {
            $errors[] = 'Invalid input type. Expected string (class name) or array';
        }

        return $errors;
    }

    /**
     * Validate a component class name
     *
     * @param string $className Class name to validate
     * @return array<string> Validation errors
     */
    protected function validateClassName(string $className): array
    {
        $errors = [];

        if ($className === '' || $className === '0') {
            $errors[] = 'Component class name cannot be empty';

            return $errors;
        }

        if (! class_exists($className)) {
            $errors[] = "Component class does not exist: {$className}";

            return $errors;
        }

        // Check if class is instantiable
        try {
            $reflection = new ReflectionClass($className);

            if (! $reflection->isInstantiable()) {
                $errors[] = "Component class is not instantiable: {$className}";
            }
        } catch (ReflectionException $e) {
            $errors[] = "Error reflecting component class: {$e->getMessage()}";
        }

        return $errors;
    }

    /**
     * Validate a component array
     *
     * @param array<int|string, mixed> $components Component array
     * @return array<string> Validation errors
     */
    protected function validateComponentArray(array $components): array
    {
        $errors = [];

        foreach ($components as $name => $class) {
            if (! is_string($name) || ($name === '' || $name === '0')) {
                $errors[] = 'Component name must be a non-empty string';

                continue;
            }

            if (! is_string($class)) {
                $errors[] = "Component class must be a string for component: {$name}";

                continue;
            }

            $classErrors = $this->validateClassName($class);
            if ($classErrors !== []) {
                $errors[] = "Invalid component '{$name}': " . implode(', ', $classErrors);
            }
        }

        return $errors;
    }

    /**
     * {@inheritDoc}
     */
    public function isValid(mixed $input): bool
    {
        return $this->validate($input) === [];
    }
}
