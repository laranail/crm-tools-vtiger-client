<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Utility;

use Simtabi\Laranail\Package\Tools\Contracts\ResolverInterface;

/**
 * Resolves and transforms namespaces between formats.
 */
class NamespaceResolver implements ResolverInterface
{
    /**
     * Resolve namespace to canonical format
     *
     * @param string $namespace Input namespace
     * @return string Resolved namespace
     */
    public function resolve(string $namespace): string
    {
        return $this->normalize($namespace);
    }

    /**
     * Normalize namespace format
     *
     * @param string $namespace Namespace to normalize
     * @return string Normalized namespace
     */
    public function normalize(string $namespace): string
    {
        $namespace = trim($namespace, '/\\');

        return str_replace('\\', '/', $namespace);
    }

    /**
     * Validate namespace format
     *
     * @param string $namespace Namespace to validate
     */
    public function validate(string $namespace): bool
    {
        if ($namespace === '' || $namespace === '0') {
            return false;
        }

        return (bool) preg_match('/^[a-zA-Z0-9\/\\\\_-]+$/', $namespace);
    }

    /**
     * Convert namespace to dashed format
     *
     * @param string $namespace Namespace to convert
     * @return string Dashed namespace (e.g., 'modules/admin' -> 'modules/admin')
     */
    public function toDashed(string $namespace): string
    {
        return str_replace(['.', '\\'], '/', $this->normalize($namespace));
    }

    /**
     * Convert namespace to dotted format
     *
     * @param string $namespace Namespace to convert
     * @return string Dotted namespace (e.g., 'modules/admin' -> 'modules.admin')
     */
    public function toDotted(string $namespace): string
    {
        return str_replace(['/', '\\'], '.', $this->normalize($namespace));
    }

    /**
     * Convert to PSR-4 namespace format
     *
     * @param string $namespace Namespace to convert
     * @return string PSR-4 namespace (e.g., 'modules/admin' -> 'Modules\\Admin')
     */
    public function toPsr4(string $namespace): string
    {
        $parts = explode('/', $this->normalize($namespace));
        $parts = array_map(ucfirst(...), $parts);

        return implode('\\', $parts);
    }

    /**
     * {@inheritDoc}
     */
    public function canResolve(string $input): bool
    {
        return $this->validate($input);
    }
}
