<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\View;

use Illuminate\Contracts\Validation\Validator as LaravelValidator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Simtabi\Laranail\Package\Tools\Contracts\ValidatorInterface;

/**
 * Validates view paths, names, and configurations.
 */
class ViewValidator implements ValidatorInterface
{
    protected ?LaravelValidator $validator = null;

    /** @var array<int, string> */
    protected array $errors = [];

    /**
     * Validate input and return array of errors (empty array if valid)
     *
     * @param mixed $input Value to validate (can be array with 'path' and 'namespace', or string path)
     * @return array<string> Array of validation errors
     */
    public function validate(mixed $input): array
    {
        $this->errors = [];

        if (is_string($input)) {
            return $this->validatePathInternal($input);
        }

        if (is_array($input)) {
            return $this->validateViewData($input);
        }

        $this->errors[] = 'Invalid input type. Expected string (path) or array (view data)';

        return $this->errors;
    }

    /**
     * Check if input is valid
     *
     * @param mixed $input Value to validate
     */
    public function isValid(mixed $input): bool
    {
        return $this->validate($input) === [];
    }

    /**
     * Validate view data array
     *
     * @param array<string, mixed> $data View data to validate
     * @param array<string, mixed> $rules Additional validation rules
     * @return array<string> Array of validation errors
     */
    protected function validateViewData(array $data, array $rules = []): array
    {
        $defaultRules = [
            'path' => ['required', 'string'],
            'namespace' => ['nullable', 'string'],
        ];

        $this->validator = Validator::make($data, array_merge($defaultRules, $rules));

        if ($this->validator->fails()) {
            $this->errors = $this->validator->errors()->all();

            return $this->errors;
        }

        if (isset($data['path']) && ! File::isDirectory($data['path'])) {
            $this->errors[] = "View path does not exist: {$data['path']}";
        }

        if (isset($data['namespace']) && ! empty($data['namespace'])) {
            $namespaceErrors = $this->validateNamespaceString($data['namespace']);
            $this->errors = array_merge($this->errors, $namespaceErrors);
        }

        return $this->errors;
    }

    /**
     * Validate view path exists (internal method)
     *
     * @param string $path Path to validate
     * @return array<string> Array of validation errors
     */
    protected function validatePathInternal(string $path): array
    {
        if ($path === '' || $path === '0') {
            $this->errors[] = 'View path cannot be empty';

            return $this->errors;
        }

        if (! File::isDirectory($path)) {
            $this->errors[] = "View directory does not exist: {$path}";
        }

        return $this->errors;
    }

    /**
     * Validate view namespace format
     *
     * @param string $namespace Namespace to validate
     * @return array<string> Array of validation errors
     */
    protected function validateNamespaceString(string $namespace): array
    {
        $errors = [];

        if (! preg_match('/^[a-zA-Z0-9\-_\/]+$/', $namespace)) {
            $errors[] = "Invalid namespace format: {$namespace}";
        }

        return $errors;
    }

    /**
     * Validate view path exists
     *
     * @param string $path Path to validate
     * @return bool True if valid, false otherwise
     */
    public function validatePath(string $path): bool
    {
        $errors = $this->validatePathInternal($path);

        return $errors === [];
    }

    /**
     * Validate view namespace format
     *
     * @param string $namespace Namespace to validate
     * @return bool True if valid, false otherwise
     */
    public function validateNamespace(string $namespace): bool
    {
        $errors = $this->validateNamespaceString($namespace);

        return $errors === [];
    }
}
