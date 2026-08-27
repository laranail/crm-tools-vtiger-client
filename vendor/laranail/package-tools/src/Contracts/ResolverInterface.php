<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Contracts;

/**
 * Resolves values such as paths, namespaces, and placeholders.
 */
interface ResolverInterface
{
    /**
     * @param string $input Value to resolve
     * @return string Resolved value
     */
    public function resolve(string $input): string;

    /**
     * @param string $input Value to check
     */
    public function canResolve(string $input): bool;
}
