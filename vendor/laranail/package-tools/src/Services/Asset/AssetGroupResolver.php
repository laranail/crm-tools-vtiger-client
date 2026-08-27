<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Asset;

use Illuminate\Support\Facades\File;
use Simtabi\Laranail\Package\Tools\Support\PathResolver;

/**
 * Resolves asset groups from configuration.
 */
class AssetGroupResolver
{
    public function __construct(protected string $basePath) {}

    /**
     * Resolve asset groups from configuration
     *
     * @param array<string, array<string, string>> $config Group configuration
     * @return array<string, array{source: string, target: string}> Resolved groups
     */
    public function resolve(array $config): array
    {
        $resolved = [];

        foreach ($config as $tag => $groupConfig) {
            $source = $groupConfig['source'] ?? '';
            $target = $groupConfig['target'] ?? '';

            $resolved[$tag] = [
                'source' => $this->resolveSourcePath($source),
                'target' => $this->resolveTargetPath($target),
            ];
        }

        return $resolved;
    }

    /**
     * Get asset groups by type
     *
     * @param string $type Asset type (js, css, media, etc.)
     * @return array<string, array<string, string>>
     */
    public function getGroups(string $type): array
    {
        $groups = [];
        $publicPath = PathResolver::joinPaths($this->basePath, 'public');

        switch ($type) {
            case 'js':
                $groups['scripts'] = [
                    'source' => PathResolver::joinPaths($publicPath, 'assets/js'),
                    'target' => 'assets/js',
                ];
                break;

            case 'css':
                $groups['styles'] = [
                    'source' => PathResolver::joinPaths($publicPath, 'assets/css'),
                    'target' => 'assets/css',
                ];
                break;

            case 'media':
                $groups['media'] = [
                    'source' => PathResolver::joinPaths($publicPath, 'assets/media'),
                    'target' => 'assets/media',
                ];
                break;

            case 'all':
                $groups['all'] = [
                    'source' => $publicPath,
                    'target' => '',
                ];
                break;
        }

        return $groups;
    }

    /**
     * Build paths for a group
     *
     * @param array{source: string, target: string} $group Group configuration
     * @return array{source: string, target: string, exists: bool}
     */
    public function buildPaths(array $group): array
    {
        $source = $this->resolveSourcePath($group['source']);
        $target = $this->resolveTargetPath($group['target']);

        return [
            'source' => $source,
            'target' => $target,
            'exists' => File::exists($source),
        ];
    }

    /**
     * Resolve source path
     *
     * @param string $path Relative path
     * @return string Absolute path
     */
    protected function resolveSourcePath(string $path): string
    {
        if ($path === '' || $path === '0') {
            return PathResolver::joinPaths($this->basePath, 'public');
        }

        return PathResolver::joinPaths($this->basePath, 'public', $path);
    }

    /**
     * Resolve target path
     *
     * @param string $path Relative path
     * @return string Full target path
     */
    protected function resolveTargetPath(string $path): string
    {
        if ($path === '' || $path === '0') {
            return public_path();
        }

        return public_path($path);
    }
}
