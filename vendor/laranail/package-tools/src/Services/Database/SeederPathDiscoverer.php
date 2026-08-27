<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Database;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use ReflectionClass;
use Simtabi\Laranail\Package\Tools\Exceptions\SeederException;
use Symfony\Component\Finder\SplFileInfo;
use Throwable;

/**
 * Walks a directory of seeder source files and yields the FQCNs of every
 * concrete `Seeder` subclass it can resolve.
 *
 * Tokeniser-based; no autoloader required — files whose classes are not
 * already autoloadable are require'd on demand (a parse error surfaces as
 * a SeederException rather than a silent drop).
 */
final class SeederPathDiscoverer
{
    /**
     * Discover every concrete Seeder subclass declared in `$path`.
     * Abstract seeders are excluded — they register fine but explode at
     * execution time.
     *
     * @return list<class-string<Seeder>>
     */
    public function discover(string $path, bool $recursive = false): array
    {
        if (! File::isDirectory($path)) {
            throw new InvalidArgumentException(
                "Seeder path does not exist or is not a directory: {$path}"
            );
        }

        $files = $recursive
            ? array_map(static fn (SplFileInfo $file): string => $file->getPathname(), File::allFiles($path))
            : (File::glob(rtrim($path, '/') . '/*.php') ?: []);

        $found = [];
        foreach ($files as $file) {
            if (! str_ends_with((string) $file, '.php')) {
                continue;
            }

            foreach ($this->classesIn((string) $file) as $fqcn) {
                if (! class_exists($fqcn)) {
                    $this->requireFile((string) $file, $path);
                }
                if (! class_exists($fqcn)) {
                    continue;
                }
                if (! is_subclass_of($fqcn, Seeder::class)) {
                    continue;
                }

                if (! (new ReflectionClass($fqcn))->isInstantiable()) {
                    continue;
                }

                $found[] = $fqcn;
            }
        }

        return array_values(array_unique($found));
    }

    private function requireFile(string $file, string $path): void
    {
        try {
            require_once $file;
        } catch (Throwable $e) {
            throw SeederException::discoveryFailed($path, "failed loading {$file}: {$e->getMessage()}");
        }
    }

    /**
     * @return list<string> Fully-qualified class names declared in `$file`.
     */
    public function classesIn(string $file): array
    {
        if (! File::isFile($file)) {
            return [];
        }

        $source = File::get($file);
        $namespace = $this->extractNamespace($source);
        $classes = [];

        $tokens = token_get_all($source);
        $count = count($tokens);

        for ($i = 2; $i < $count; $i++) {
            if (! is_array($tokens[$i - 2])) {
                continue;
            }
            if ($tokens[$i - 2][0] !== T_CLASS) {
                continue;
            }
            if (! is_array($tokens[$i - 1])) {
                continue;
            }
            if ($tokens[$i - 1][0] !== T_WHITESPACE) {
                continue;
            }
            if (! is_array($tokens[$i])) {
                continue;
            }
            if ($tokens[$i][0] !== T_STRING) {
                continue;
            }
            $name = $tokens[$i][1];
            $classes[] = $namespace === '' ? $name : "{$namespace}\\{$name}";
        }

        return $classes;
    }

    private function extractNamespace(string $source): string
    {
        if (preg_match('/^\s*namespace\s+([^;{]+)\s*[;{]/m', $source, $m) === 1) {
            return trim($m[1]);
        }

        return '';
    }
}
