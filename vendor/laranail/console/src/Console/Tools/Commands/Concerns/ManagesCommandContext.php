<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Commands\Concerns;

/**
 * A small key/value context bag shared by the command logger and error services.
 * (Each service exposes its own `getContext()`: the logger enriches the bag with
 * command name + timestamp, the error service returns it raw.)
 *
 * @internal Shared implementation of the command services; not a public extension point.
 */
trait ManagesCommandContext
{
    /** @var array<string, mixed> */
    protected array $context = [];

    public function addContext(string $key, mixed $value): self
    {
        $this->context[$key] = $value;

        return $this;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function addContextMany(array $context): self
    {
        $this->context = array_merge($this->context, $context);

        return $this;
    }

    public function clearContext(): self
    {
        $this->context = [];

        return $this;
    }
}
