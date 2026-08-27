<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Config;

use Illuminate\Support\Facades\File;
use Simtabi\Laranail\Package\Tools\Contracts\ResolverInterface;
use Simtabi\Laranail\Package\Tools\Exceptions\InvalidPath;
use Simtabi\Laranail\Package\Tools\Support\PathResolver;

/**
 * Resolves configuration file paths, including nested configs.
 */
class ConfigFileResolver implements ResolverInterface
{
    public function __construct(protected string $basePath) {}

    /**
     * Resolve a configuration file path
     *
     * @param string $file Config file name (without .php extension)
     * @return string Full path to config file
     */
    public function resolve(string $file): string
    {
        $file = trim($file, '/\\');
        // `..` segments and null bytes would let an embedded value escape the
        // package's config/ tree once joinPaths()/normalizePath() collapse
        // them; reject before building the path.
        PathResolver::validatePathSecurity($file);
        $path = PathResolver::joinPaths($this->basePath, 'config', $file . '.php');

        return PathResolver::normalizePath($path);
    }

    /**
     * Resolve a nested configuration file path
     *
     * @param string $file Config file name (without .php extension)
     * @param string $folder Subdirectory within config folder
     * @return string Full path to config file
     */
    public function resolveNested(string $file, string $folder): string
    {
        $folder = trim($folder, '/\\');
        $file = trim($file, '/\\');

        if ($folder === '' || $folder === '0') {
            return $this->resolve($file);
        }

        PathResolver::validatePathSecurity($folder);
        PathResolver::validatePathSecurity($file);
        $path = PathResolver::joinPaths($this->basePath, 'config', $folder, $file . '.php');

        return PathResolver::normalizePath($path);
    }

    /**
     * Convert a config path relative to config/ (without extension) into a
     * dotted config key. `api/v1/limits` → `api.v1.limits`.
     *
     * @param string $relativeNoExt Path relative to config/, no `.php`
     * @return string Dotted config key
     */
    public function folderToKey(string $relativeNoExt): string
    {
        $relativeNoExt = trim(str_replace('\\', '/', $relativeNoExt), '/');
        PathResolver::validatePathSecurity($relativeNoExt);

        return str_replace('/', '.', $relativeNoExt);
    }

    /**
     * Recursively list every `.php` config file under config/{folder},
     * returned as paths relative to config/ (e.g. 'admin/panel.php').
     *
     * @param string $folder Subdirectory within config/ ('' = whole tree)
     * @return array<int, string> Relative file paths, sorted
     */
    public function getAllNested(string $folder = ''): array
    {
        $folder = trim($folder, '/\\');
        if ($folder !== '') {
            PathResolver::validatePathSecurity($folder);
        }

        $scanRoot = $folder === ''
            ? PathResolver::joinPaths($this->basePath, 'config')
            : PathResolver::joinPaths($this->basePath, 'config', $folder);

        if (! File::isDirectory($scanRoot)) {
            return [];
        }

        $relatives = [];

        foreach (File::allFiles($scanRoot) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relName = str_replace('\\', '/', $file->getRelativePathname());
            $relatives[] = $folder === '' ? $relName : $folder . '/' . $relName;
        }

        sort($relatives);

        return $relatives;
    }

    /**
     * Load a single nested config file and RETURN its array — without
     * registering it in Laravel's config repository. The read-and-return
     * counterpart of the Package builder's hasNestedConfig() (which mounts).
     *
     * @param string $file Config file name without `.php` (e.g. 'panel')
     * @param string $folder Subdirectory within config/ (e.g. 'admin', 'api/v1')
     * @return array<string, mixed> The file's returned array
     *
     * @throws InvalidPath If the file is missing/unreadable or does not return an array
     */
    public function load(string $file, string $folder = ''): array
    {
        return $this->requireArray($this->resolveNested($file, $folder));
    }

    /**
     * Load every config file under config/{folder} and RETURN them keyed by the
     * folder-derived dotted key — the same keys discoversConfig() would mount
     * them at (config/admin/panel.php → ['admin.panel' => [...]]). Nothing is
     * registered in the config repository; raw file data is returned as-is (an
     * in-file `__namespace` key is NOT stripped — that is a mount-time concern).
     *
     * Returns [] when the folder does not exist.
     *
     * @param string $folder Subdirectory within config/ ('' = the whole tree)
     * @param bool $recursive Descend into sub-folders (true) or top level only (false)
     * @return array<string, array<string, mixed>> Map of dotted key => config array
     *
     * @throws InvalidPath If any matched file is unreadable or does not return an array
     */
    public function loadAll(string $folder = '', bool $recursive = true): array
    {
        $relatives = $recursive ? $this->getAllNested($folder) : $this->topLevelFiles($folder);

        $loaded = [];

        foreach ($relatives as $relative) {
            $relativeNoExt = (string) preg_replace('/\.php$/', '', $relative);
            $path = PathResolver::normalizePath(
                PathResolver::joinPaths($this->basePath, 'config', $relative)
            );

            $loaded[$this->folderToKey($relativeNoExt)] = $this->requireArray($path);
        }

        return $loaded;
    }

    /**
     * List the top-level `.php` files directly under config/{folder} as paths
     * relative to config/ (mirrors getAllNested()'s shape, one level only).
     *
     * @param string $folder Subdirectory within config/
     * @return array<int, string> Relative file paths, sorted
     */
    private function topLevelFiles(string $folder): array
    {
        $folder = trim($folder, '/\\');

        $relatives = array_map(
            static fn (string $name): string => $folder === '' ? $name . '.php' : $folder . '/' . $name . '.php',
            $this->getAllInDirectory($folder),
        );

        sort($relatives);

        return $relatives;
    }

    /**
     * Require a config file path and assert it returns an array.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidPath
     */
    private function requireArray(string $path): array
    {
        if (! File::isFile($path) || ! File::isReadable($path)) {
            throw InvalidPath::configFileNotReadable($path);
        }

        $data = require $path;

        if (! is_array($data)) {
            throw InvalidPath::configFileNotArray($path);
        }

        return $data;
    }

    /**
     * Check if a configuration file exists
     *
     * @param string $file Config file name
     */
    public function exists(string $file): bool
    {
        $path = $this->resolve($file);

        return File::exists($path);
    }

    /**
     * Get all configuration files in a directory
     *
     * @param string $directory Directory path relative to config
     * @return array<string> Array of config file names
     */
    public function getAllInDirectory(string $directory = ''): array
    {
        $directory = trim($directory, '/\\');
        if ($directory !== '') {
            PathResolver::validatePathSecurity($directory);
        }
        $configPath = PathResolver::joinPaths($this->basePath, 'config', $directory);

        if (! File::isDirectory($configPath)) {
            return [];
        }

        $files = File::files($configPath);
        $configFiles = [];

        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $configFiles[] = $file->getFilenameWithoutExtension();
            }
        }

        return $configFiles;
    }

    /**
     * {@inheritDoc}
     */
    public function canResolve(string $input): bool
    {
        return $input !== '' && $input !== '0';
    }
}
