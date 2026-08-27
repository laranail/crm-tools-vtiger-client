<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Package;

use Illuminate\Contracts\Validation\Validator as LaravelValidator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Simtabi\Laranail\Package\Tools\Contracts\ValidatorInterface;

/**
 * Validates package structure, naming, and configuration.
 */
class PackageValidator implements ValidatorInterface
{
    protected ?LaravelValidator $validator = null;

    /** @var array<int, string> */
    protected array $errors = [];

    /**
     * Validate input and return array of errors (empty array if valid)
     *
     * @param mixed $input Value to validate (can be array with package data, or string path)
     * @return array<string> Array of validation errors
     */
    public function validate(mixed $input): array
    {
        $this->errors = [];

        if (is_string($input)) {
            return $this->validatePathString($input);
        }

        if (is_array($input)) {
            return $this->validatePackageData($input);
        }

        $this->errors[] = 'Invalid input type. Expected string (path) or array (package data)';

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
     * Validate package data array
     *
     * @param array<string, mixed> $data Package data to validate
     * @param array<string, mixed> $rules Additional validation rules
     * @return array<string> Array of validation errors
     */
    protected function validatePackageData(array $data, array $rules = []): array
    {
        $defaultRules = [
            'name' => ['required', 'string', 'regex:/^[a-z0-9\-]+\/[a-z0-9\-]+$/'],
            'path' => ['required', 'string'],
            'namespace' => ['required', 'string'],
        ];

        $this->validator = Validator::make($data, array_merge($defaultRules, $rules));

        if ($this->validator->fails()) {
            $this->errors = $this->validator->errors()->all();

            return $this->errors;
        }

        if (isset($data['path'])) {
            $structureErrors = $this->validatePathString($data['path']);
            $this->errors = array_merge($this->errors, $structureErrors);
        }

        return $this->errors;
    }

    /**
     * Validate path string
     *
     * @param string $path Path to validate
     * @return array<string> Array of validation errors
     */
    protected function validatePathString(string $path): array
    {
        if ($path === '' || $path === '0') {
            $this->errors[] = 'Path cannot be empty';

            return $this->errors;
        }

        $structureErrors = $this->validateStructureInternal($path);
        $this->errors = array_merge($this->errors, $structureErrors);

        return $this->errors;
    }

    /**
     * Validate package directory structure (internal method)
     *
     * @param string $path Package path
     * @return array<string> Array of validation errors
     */
    protected function validateStructureInternal(string $path): array
    {
        $errors = [];
        $requiredFiles = ['composer.json'];
        $recommendedDirs = ['src'];

        foreach ($requiredFiles as $file) {
            if (! File::exists($path . '/' . $file)) {
                $errors[] = "Required file missing: {$file}";
            }
        }

        foreach ($recommendedDirs as $dir) {
            if (! File::isDirectory($path . '/' . $dir)) {
                $errors[] = "Recommended directory missing: {$dir}";
            }
        }

        return $errors;
    }

    /**
     * Validate package directory structure
     *
     * @param string $path Package path
     * @return bool True if valid, false otherwise
     */
    public function validateStructure(string $path): bool
    {
        $errors = $this->validateStructureInternal($path);

        return $errors === [];
    }

    /**
     * Validate package name format
     *
     * @param string $name Package name (vendor/package)
     * @return bool True if valid, false otherwise
     */
    public function validateName(string $name): bool
    {
        return (bool) preg_match('/^[a-z0-9\-]+\/[a-z0-9\-]+$/', $name);
    }

    /**
     * Validate PSR-4 namespace
     *
     * @param string $namespace Namespace to validate
     * @return bool True if valid, false otherwise
     */
    public function validateNamespace(string $namespace): bool
    {
        return (bool) preg_match('/^[A-Z][a-zA-Z0-9]*(\\\\[A-Z][a-zA-Z0-9]*)*$/', $namespace);
    }
}
