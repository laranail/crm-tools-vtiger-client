<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Concerns\Package;

use Closure;
use Illuminate\Support\Facades\Gate;

/**
 * Declarative authorization gates, mirroring {@see HasRateLimiters}. Gate
 * closures run per check, so config reads inside them are already lazy.
 */
trait HasGates
{
    /** @var array<string, Closure> */
    protected array $gates = [];

    public function registerGate(string $ability, Closure $callback): static
    {
        $this->gates[$ability] = $callback;

        return $this;
    }

    /**
     * @param array<string, Closure> $gates [ability => callback]
     */
    public function registerGates(array $gates): static
    {
        foreach ($gates as $ability => $callback) {
            $this->registerGate($ability, $callback);
        }

        return $this;
    }

    public function bootPackageGates(): void
    {
        foreach ($this->gates as $ability => $callback) {
            Gate::define($ability, $callback);
        }
    }

    /**
     * @return array<string, Closure>
     */
    public function getGates(): array
    {
        return $this->gates;
    }
}
