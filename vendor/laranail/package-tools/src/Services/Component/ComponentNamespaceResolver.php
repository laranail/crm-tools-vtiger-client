<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Component;

use Illuminate\Support\Str;
use Simtabi\Laranail\Package\Tools\Contracts\ResolverInterface;
use Simtabi\Laranail\Package\Tools\Services\Config\ConfigService;
use Simtabi\Laranail\Package\Tools\Services\Config\PatternResolver;

/**
 * Resolves and normalizes component namespaces and prefixes using
 * configured patterns.
 */
class ComponentNamespaceResolver implements ResolverInterface
{
    public function __construct(
        protected ConfigService $config,
        protected PatternResolver $patternResolver
    ) {}

    /**
     * Resolve a component namespace
     *
     * @param string $namespace Namespace to resolve (e.g., 'modules/theme')
     * @param array<string, mixed> $data Additional data for resolution
     * @return string Resolved namespace (e.g., 'App\Theme\View\Components')
     */
    public function resolve(string $namespace, array $data = []): string
    {
        $pattern = $this->config->get(
            'packager.patterns.component_namespace',
            '{project}\\{module}\\{component_ns}'
        );

        $projectNs = $this->config->get('packager.project.namespace', 'App');
        $componentNs = $this->config->get('packager.namespaces.view_components', 'View\\Components');

        $parts = explode('/', trim($namespace, '/'));
        $moduleName = Str::studly(end($parts));

        return $this->patternResolver->resolve($pattern, [
            'project' => $projectNs,
            'module' => $moduleName,
            'component_ns' => $componentNs,
        ]);
    }

    /**
     * Normalize a component prefix, converting directory separators to
     * hyphens for Blade syntax.
     *
     * @param string $prefix Prefix to normalize
     * @return string Normalized prefix
     */
    public function normalize(string $prefix): string
    {
        return str_replace('/', '-', trim($prefix, '/'));
    }

    /**
     * Build a component namespace from module name
     *
     * @param string $module Module name
     * @return string Component namespace
     */
    public function buildNamespace(string $module): string
    {
        $pattern = $this->config->get(
            'packager.patterns.component_namespace',
            '{project}\\{module}\\{component_ns}'
        );

        $projectNs = $this->config->get('packager.project.namespace', 'App');
        $componentNs = $this->config->get('packager.namespaces.view_components', 'View\\Components');

        return $this->patternResolver->resolve($pattern, [
            'project' => $projectNs,
            'module' => Str::studly($module),
            'component_ns' => $componentNs,
        ]);
    }

    /**
     * Extract the prefix part from a full namespace.
     *
     * @param string $namespace Full namespace
     * @return string Prefix
     */
    public function getPrefix(string $namespace): string
    {
        // Extract module name from namespace and convert to kebab-case
        $parts = explode('\\', trim($namespace, '\\'));

        if (count($parts) >= 2) {
            $moduleName = $parts[1];

            return Str::kebab($moduleName);
        }

        return str_replace('/', '-', $namespace);
    }

    /**
     * {@inheritDoc}
     */
    public function canResolve(string $input): bool
    {
        return $input !== '' && $input !== '0';
    }
}
