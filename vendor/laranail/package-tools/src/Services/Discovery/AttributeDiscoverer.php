<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Discovery;

use FilesystemIterator;
use Generator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionAttribute;
use ReflectionClass;
use RuntimeException;
use SplFileInfo;

/**
 * Walks a directory tree, parses PHP files, and yields ReflectionClass
 * objects for every class carrying the requested attribute.
 *
 * Used by Package::discoversWithAttributes() to wire commands, routes,
 * facades, and view composers without explicit `hasCommand()` calls.
 * Stdlib-only, no extra discovery dependency.
 */
final class AttributeDiscoverer
{
    /**
     * Yield every class under $directory that carries $attributeClass.
     *
     * @template T of object
     *
     * @param string $directory Absolute path to scan recursively.
     * @param string $rootNamespace PSR-4 root for $directory (e.g. "App\\").
     * @param class-string<T> $attributeClass Attribute to look for.
     * @return Generator<int, array{class: ReflectionClass<object>, attributes: list<ReflectionAttribute<T>>}>
     */
    public function discover(string $directory, string $rootNamespace, string $attributeClass): Generator
    {
        if (! is_dir($directory)) {
            throw new RuntimeException(
                "AttributeDiscoverer: directory does not exist: {$directory}"
            );
        }

        $rootNamespace = rtrim($rootNamespace, '\\') . '\\';
        $directory = rtrim($directory, '/');

        foreach ($this->phpFiles($directory) as $file) {
            $className = $this->classNameFromFile($file, $directory, $rootNamespace);
            if ($className === null) {
                continue;
            }

            // Accept classes, interfaces, traits, and enums (contracts are
            // typically interfaces). The *_exists() checks also autoload the
            // symbol and narrow it to a valid ReflectionClass target.
            if (! class_exists($className) && ! interface_exists($className) && ! trait_exists($className) && ! enum_exists($className)) {
                continue;
            }

            $rc = new ReflectionClass($className);

            $attrs = $rc->getAttributes($attributeClass, ReflectionAttribute::IS_INSTANCEOF);
            if ($attrs === []) {
                continue;
            }

            yield [
                'class' => $rc,
                'attributes' => $attrs,
            ];
        }
    }

    /**
     * Yield the FQCN of every instantiable class under $directory that is a
     * subclass of $parentClass.
     *
     * Reuses the same file walk + class-name derivation as discover(), but
     * filters on inheritance instead of attributes. Abstract classes,
     * interfaces, traits, and unrelated classes are skipped.
     *
     * @param string $directory Absolute path to scan recursively.
     * @param string $rootNamespace PSR-4 root for $directory (e.g. "App\\").
     * @param class-string $parentClass Ancestor class to match against.
     * @return Generator<int, class-string>
     */
    public function discoverSubclasses(string $directory, string $rootNamespace, string $parentClass): Generator
    {
        if (! is_dir($directory)) {
            throw new RuntimeException(
                "AttributeDiscoverer: directory does not exist: {$directory}"
            );
        }

        $rootNamespace = rtrim($rootNamespace, '\\') . '\\';
        $directory = rtrim($directory, '/');

        foreach ($this->phpFiles($directory) as $file) {
            $className = $this->classNameFromFile($file, $directory, $rootNamespace);
            if ($className === null) {
                continue;
            }

            // class_exists() also autoloads the symbol and narrows it to a
            // valid ReflectionClass target.
            if (! class_exists($className)) {
                continue;
            }

            $rc = new ReflectionClass($className);
            if (! $rc->isInstantiable()) {
                continue;
            }
            if (! $rc->isSubclassOf($parentClass)) {
                continue;
            }

            yield $className;
        }
    }

    /**
     * Walk $directory and yield every *.php file (depth-first).
     *
     * @return Generator<SplFileInfo>
     */
    private function phpFiles(string $directory): Generator
    {
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iter as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                yield $file;
            }
        }
    }

    /**
     * Derive a fully-qualified class name from a file path under a PSR-4 root.
     *
     * Returns null when the file's path/namespace can't be resolved (e.g.,
     * file outside $directory, or no class declared).
     */
    private function classNameFromFile(SplFileInfo $file, string $directory, string $rootNamespace): ?string
    {
        $absolute = $file->getPathname();
        if (! str_starts_with($absolute, $directory . '/')) {
            return null;
        }

        $relative = substr($absolute, strlen($directory) + 1);
        $relative = substr($relative, 0, -strlen('.php'));

        $fqcn = $rootNamespace . str_replace('/', '\\', $relative);

        return $fqcn !== $rootNamespace ? $fqcn : null;
    }
}
