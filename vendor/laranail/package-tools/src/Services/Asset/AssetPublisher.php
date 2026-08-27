<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Asset;

use Closure;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Simtabi\Laranail\Package\Tools\Contracts\PublisherInterface;
use Simtabi\Laranail\Package\Tools\Services\Config\ConfigService;
use Simtabi\Laranail\Package\Tools\Services\Config\PatternResolver;
use Simtabi\Laranail\Package\Tools\Support\PathResolver;

/**
 * Publishes assets from package to application, using configured
 * patterns for publish tags.
 */
class AssetPublisher implements PublisherInterface
{
    /** @var array<string, string> */
    protected array $published = [];

    /**
     * @param Closure(array<string, string>, string): void $publishUsing
     *                                                                   Registers publish paths under a tag. The owning service provider
     *                                                                   supplies a closure bound to its own scope so it can reach the
     *                                                                   protected ServiceProvider::publishes() legitimately, e.g.
     *                                                                   `fn (array $paths, string $tag) => $this->publishes($paths, $tag)`.
     */
    public function __construct(protected Closure $publishUsing, protected AssetRegistry $registry, protected ConfigService $config, protected PatternResolver $patternResolver) {}

    /**
     * {@inheritDoc}
     */
    public function publish(string $source, string $target, string $tag): void
    {
        if (! $this->canPublish($source)) {
            return;
        }

        ($this->publishUsing)([$source => $target], $tag);
        $this->published[$source] = $target;
        $this->registry->register($tag, $target, false);
    }

    /**
     * Publish a group of assets
     *
     * @param array<string, string> $sources Array of source => target paths
     * @param string $tag Publish tag
     */
    public function publishGroup(array $sources, string $tag): void
    {
        $validSources = [];

        foreach ($sources as $source => $target) {
            if ($this->canPublish($source)) {
                $validSources[$source] = $target;
                $this->published[$source] = $target;
            }
        }

        if ($validSources !== []) {
            ($this->publishUsing)($validSources, $tag);

            foreach ($validSources as $target) {
                $this->registry->register($tag, $target, false);
            }
        }
    }

    /**
     * Publish module assets using conventions
     *
     * @param array<string>|null $types Asset types to publish (null = all)
     * @param string $basePath Base publish path
     * @param string $moduleName Module name
     */
    public function publishModuleAssets(?array $types, string $basePath, string $moduleName): void
    {
        $standardTypes = [
            'all' => ['source' => '', 'target' => ''],
            'js' => ['source' => 'assets/js', 'target' => 'assets/js'],
            'css' => ['source' => 'assets/css', 'target' => 'assets/css'],
            'media' => ['source' => 'assets/media', 'target' => 'assets/media'],
            'vendors' => ['source' => 'assets/vendors', 'target' => 'assets/vendors'],
        ];

        if ($types !== null) {
            $standardTypes = array_intersect_key($standardTypes, array_flip($types));
        }

        foreach ($standardTypes as $type => $config) {
            $tag = $this->resolveTag($moduleName, $type);
            $source = $config['source'] ? PathResolver::joinPaths($basePath, 'public', $config['source']) : PathResolver::joinPaths($basePath, 'public');
            $target = $config['target'] ? public_path("vendor/{$moduleName}/{$config['target']}") : public_path("vendor/{$moduleName}");

            if (File::isDirectory($source)) {
                $this->publish($source, $target, $tag);
            }
        }
    }

    /**
     * Resolve publish tag using configured pattern
     *
     * @param string $module Module name
     * @param string $type Asset type
     * @return string Resolved tag
     */
    protected function resolveTag(string $module, string $type): string
    {
        $pattern = $this->config->get(
            'packager.patterns.publish_tag',
            '{prefix}-{module_kebab}-{type}'
        );

        $prefix = $this->config->get('packager.project.tag_prefix', 'app');

        return $this->patternResolver->resolve($pattern, [
            'prefix' => $prefix,
            'module' => $module,
            'module_kebab' => Str::kebab($module),
            'type' => $type,
        ]);
    }

    /**
     * Register asset for cleanup before publishing
     *
     * @param string $target Target path
     * @param string $tag Publish tag
     */
    public function registerForCleanup(string $target, string $tag): void
    {
        $this->registry->register($tag, $target, true);
    }

    /**
     * {@inheritDoc}
     */
    public function canPublish(string $source): bool
    {
        return File::exists($source) && (File::isFile($source) || File::isDirectory($source));
    }

    /**
     * {@inheritDoc}
     */
    public function getPublished(): array
    {
        return $this->published;
    }
}
