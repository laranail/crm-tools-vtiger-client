<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Illuminate\Support\Facades\File;
use ReflectionClass;
use RuntimeException;
use Simtabi\Laranail\Package\Tools\Support\Resilience\FailurePolicy;

/**
 * Path resolution with security validation. Blocks path traversal, absolute
 * paths, and drive letters; auto-detects the package root via reflection.
 */
trait HasAdvancedPaths
{
    /**
     * Package base path, with an optional validated subpath appended.
     *
     * @param string|null $path Optional subpath to append (relative path only)
     * @param string $basePath Base path prefix (default: 'platform')
     * @return string Full validated path
     *
     * @throws RuntimeException If path validation fails
     *
     * @example $this->getPath('config/app.php'); // .../packages/blog/config/app.php
     */
    protected function getPath(?string $path = null, string $basePath = 'platform'): string
    {
        if ($path !== null) {
            $path = $this->validatePathSecurity($path);
        }

        $packageBase = $this->buildBasePath($basePath);

        if ($path === null || $path === '') {
            return $packageBase;
        }

        return $packageBase . DIRECTORY_SEPARATOR . $path;
    }

    /**
     * Reject path traversal (..), absolute paths, drive letters, and null
     * bytes; return the trimmed, normalized path.
     *
     * @param string $path Path to validate
     * @return string Validated path (trimmed and normalized)
     *
     * @throws RuntimeException If path fails security checks
     */
    protected function validatePathSecurity(string $path): string
    {
        $originalPath = $path;
        $path = trim($path);

        if ($path === '') {
            return '';
        }

        $errors = [];

        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            $errors[] = 'starts with a slash (absolute path)';
        }

        if (preg_match('/^[A-Z]:[\\\\\/]/i', $path)) {
            $errors[] = 'contains a Windows drive letter (e.g. C:)';
        }

        if (str_contains($path, '..')) {
            $errors[] = 'contains parent directory references (..)';
        }

        if (str_contains($path, "\0")) {
            $errors[] = 'contains null bytes';
        }

        if ($errors !== []) {
            $reason = implode(', ', $errors);
            throw new RuntimeException(
                "Invalid path '{$originalPath}': Path {$reason}. " .
                "Use relative paths within the package like 'config/file.php' or 'resources/views'."
            );
        }

        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    /**
     * Resolve the package base path: reflection-based detection (find 'src'),
     * falling back to the namespace-based path.
     *
     * @param string $basePath Base path prefix
     * @return string Package base path
     */
    protected function buildBasePath(string $basePath = 'platform'): string
    {
        $reflection = new ReflectionClass($this);
        $classFile = $reflection->getFileName();

        if ($classFile !== false) {
            try {
                return $this->resolveModulePath($classFile, $basePath);
            } catch (RuntimeException $e) {
                // A fired fallback (rule 14): reflection-based module-root
                // resolution failed, so we fall back to namespace-based path
                // derivation — worth a warning before it becomes a failure.
                FailurePolicy::warn('module path resolution fell back', [
                    'expected' => 'reflection-based module root from ' . $basePath,
                    'actual' => 'threw ' . $e::class . ', using namespace-based path',
                    'decision' => 'tolerated fallback',
                ]);
            }
        }

        return $this->buildNamespaceBasedPath($basePath);
    }

    /**
     * Walk up from the class file to the 'src' directory and return its parent
     * as the module root.
     *
     * @param string $classFile Full path to the class file
     * @param string $basePath Base path prefix
     * @return string Module root path
     *
     * @throws RuntimeException If 'src' directory not found
     */
    protected function resolveModulePath(string $classFile, string $basePath): string
    {
        $directory = File::dirname($classFile);

        $parts = explode(DIRECTORY_SEPARATOR, $directory);

        $srcIndex = array_search('src', $parts, true);

        if ($srcIndex !== false && $srcIndex > 0) {
            $moduleRoot = implode(DIRECTORY_SEPARATOR, array_slice($parts, 0, $srcIndex));

            if (File::isDirectory($moduleRoot)) {
                return $moduleRoot;
            }
        }

        return $this->buildNamespaceBasedPath($basePath);
    }

    /**
     * Build the path from the package namespace, assuming it maps to the
     * directory structure.
     *
     * @param string $basePath Base path prefix
     * @return string Namespace-based path
     */
    protected function buildNamespaceBasedPath(string $basePath): string
    {
        $namespace = $this->getDashedNamespace();

        $basePath = trim($basePath, '/\\');

        if ($basePath !== '') {
            return base_path($basePath . DIRECTORY_SEPARATOR . $namespace);
        }

        return base_path($namespace);
    }

    /**
     * Package namespace in dashed format (e.g. 'packages/blog'), implemented
     * by the Package class.
     *
     * @return string Dashed namespace
     */
    abstract protected function getDashedNamespace(): string;

    /**
     * Detect the module root from the current class file.
     *
     * @return string Module root path
     *
     * @throws RuntimeException If detection fails
     */
    protected function detectModuleRoot(): string
    {
        $reflection = new ReflectionClass($this);
        $classFile = $reflection->getFileName();

        if ($classFile === false) {
            throw new RuntimeException('Could not determine class file path for module root detection');
        }

        return $this->resolveModulePath($classFile, 'platform');
    }

    /**
     * @param string|null $path Path to check (relative to package root)
     */
    protected function isDirectory(?string $path): bool
    {
        if ($path === null) {
            return false;
        }

        try {
            $fullPath = $this->getPath($path);

            return File::isDirectory($fullPath);
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * @param string|null $path Path to check (relative to package root)
     */
    protected function isFile(?string $path): bool
    {
        if ($path === null) {
            return false;
        }

        try {
            $fullPath = $this->getPath($path);

            return File::isFile($fullPath);
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Resolve several paths in one call; invalid paths are skipped.
     *
     * @param array<string, string> $paths Map of key => path
     * @return array<string, string> Map of key => full path
     */
    protected function getPaths(array $paths): array
    {
        $result = [];

        foreach ($paths as $key => $path) {
            try {
                $result[$key] = $this->getPath($path);
            } catch (RuntimeException) {
                continue;
            }
        }

        return $result;
    }
}
