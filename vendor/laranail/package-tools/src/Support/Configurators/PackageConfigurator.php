<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support\Configurators;

use Simtabi\Laranail\Package\Tools\Package;

/**
 * Minimal base for the fluent sub-builders returned from the {@see Package}
 * builder (`->paginator()`, `->event()`). It holds the package and
 * transparently forwards any method it does not define back to the package,
 * so a chain can flow between the sub-builder and the package without an
 * explicit hop:
 *
 *     $package->paginator()->setViews(…)->useHttps()   // useHttps() is on Package
 *
 * When the forwarded package method returns the package itself (the fluent
 * `static` return), the sub-builder returns *itself* instead so the chain
 * stays on the sub-builder; any other return value is passed through.
 *
 * @mixin Package
 */
abstract class PackageConfigurator
{
    public function __construct(protected readonly Package $package) {}

    /**
     * @param array<int, mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        $result = $this->package->{$name}(...$arguments);

        return $result === $this->package ? $this : $result;
    }

    /**
     * Return the underlying package builder (end the sub-builder chain).
     */
    public function package(): Package
    {
        return $this->package;
    }
}
