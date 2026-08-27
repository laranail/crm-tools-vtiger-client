<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Utility;

use Simtabi\Laranail\Package\Tools\Contracts\ValidatorInterface;

/**
 * Validates paths for security and cross-platform compatibility.
 */
class PathValidator implements ValidatorInterface
{
    /** @var array<int, string> */
    protected array $errors = [];

    /**
     * Validate input and return array of errors (empty array if valid)
     *
     * @param mixed $input Value to validate (can be string path or array with 'path' key)
     * @return array<string> Array of validation errors
     */
    public function validate(mixed $input): array
    {
        $this->errors = [];

        if (is_string($input)) {
            return $this->validatePathString($input);
        }

        if (is_array($input)) {
            if (! isset($input['path'])) {
                $this->errors[] = 'Path is required';

                return $this->errors;
            }

            return $this->validatePathString($input['path']);
        }

        $this->errors[] = 'Invalid input type. Expected string (path) or array (path data)';

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
     * Validate a single path string
     *
     * @param string $path Path to validate
     * @return array<string> Array of validation errors
     */
    protected function validatePathString(string $path): array
    {
        $this->errors = [];

        if ($path === '' || $path === '0') {
            $this->errors[] = 'Path cannot be empty';

            return $this->errors;
        }

        if ($this->hasDirectoryTraversal($path)) {
            $this->errors[] = 'Path contains directory traversal sequences';
        }

        if (str_contains($path, "\0")) {
            $this->errors[] = 'Path contains null bytes';
        }

        return $this->errors;
    }

    /**
     * Validate a single path
     *
     * @param string $path Path to validate
     * @return bool True if valid, false otherwise
     */
    public function validatePath(string $path): bool
    {
        $errors = $this->validate($path);

        return $errors === [];
    }

    /**
     * Validate cross-platform path
     *
     * @param string $path Path to validate
     * @return array<string, mixed> Validation results with warnings
     */
    public function validateCrossPlatform(string $path): array
    {
        $issues = [];
        $warnings = [];

        if (DIRECTORY_SEPARATOR === '/' && str_contains($path, '\\')) {
            $warnings[] = 'Path contains backslashes on Unix system';
        }

        if (preg_match('/[<>:"|?*]/', $path)) {
            $issues[] = 'Path contains characters invalid on Windows';
        }

        if (rtrim($path) !== $path) {
            $warnings[] = 'Path has trailing spaces';
        }

        // Reserved device names on Windows.
        $basename = basename($path);
        $reserved = ['CON', 'PRN', 'AUX', 'NUL', 'COM1', 'COM2', 'COM3', 'COM4', 'LPT1', 'LPT2', 'LPT3'];
        if (in_array(strtoupper($basename), $reserved, true)) {
            $issues[] = 'Path uses reserved name on Windows';
        }

        return [
            'valid' => $issues === [],
            'issues' => $issues,
            'warnings' => $warnings,
        ];
    }

    /**
     * Check if path contains directory traversal sequences
     *
     * @param string $path Path to check
     */
    protected function hasDirectoryTraversal(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);

        if (str_contains($normalized, '../') || str_contains($normalized, '/..')) {
            return true;
        }

        return str_starts_with($normalized, '/..') || str_starts_with($normalized, '..');
    }

    /**
     * Sanitize path for safe usage
     *
     * @param string $path Path to sanitize
     * @return string Sanitized path
     */
    public function sanitize(string $path): string
    {
        $path = str_replace("\0", '', $path);
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        // Collapse duplicate separators. preg_replace() returns null only on
        // engine error; fall back to the pre-collapse path so we return a string.
        return preg_replace('#' . preg_quote(DIRECTORY_SEPARATOR, '#') . '+#', DIRECTORY_SEPARATOR, $path) ?? $path;
    }
}
