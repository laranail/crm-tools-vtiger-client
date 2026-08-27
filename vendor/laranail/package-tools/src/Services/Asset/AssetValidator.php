<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Asset;

use Illuminate\Support\Facades\File;
use Simtabi\Laranail\Package\Tools\Contracts\ValidatorInterface;
use Throwable;

/**
 * Validates asset files and directories.
 */
class AssetValidator implements ValidatorInterface
{
    /**
     * {@inheritDoc}
     */
    public function validate(mixed $input): array
    {
        $errors = [];

        if (! is_string($input)) {
            $errors[] = 'Asset path must be a string';

            return $errors;
        }

        if (! File::exists($input)) {
            $errors[] = "Asset does not exist: {$input}";

            return $errors;
        }

        if (! File::isReadable($input)) {
            $errors[] = "Asset is not readable: {$input}";
        }

        if (File::isDirectory($input)) {
            return array_merge($errors, $this->validateDirectory($input));
        }

        return array_merge($errors, $this->validateFile($input));
    }

    /**
     * Validate asset directory
     *
     * @param string $directory Directory path
     * @return array<string> Validation errors
     */
    protected function validateDirectory(string $directory): array
    {
        $errors = [];

        try {
            $files = File::allFiles($directory);

            if (empty($files)) {
                $errors[] = "Asset directory is empty: {$directory}";
            }
        } catch (Throwable $e) {
            $errors[] = "Error reading asset directory: {$e->getMessage()}";
        }

        return $errors;
    }

    /**
     * Validate asset file
     *
     * @param string $file File path
     * @return array<string> Validation errors
     */
    protected function validateFile(string $file): array
    {
        $errors = [];

        $size = File::size($file);
        if ($size === 0) {
            $errors[] = "Asset file is empty: {$file}";
        }

        // Check for excessively large files (> 10MB)
        if ($size > 10 * 1024 * 1024) {
            $errors[] = "Asset file is too large (> 10MB): {$file}";
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
