<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Exceptions;

use RuntimeException;
use Simtabi\Laranail\Package\Tools\Enums\BootCriticality;
use Throwable;

/**
 * Wraps a failure in a package's boot-time WIRING with the name of the
 * builder that failed and its {@see BootCriticality}, so a report points
 * straight at the culprit instead of a bare trace deep in a closure.
 *
 * The original cause is preserved in the exception chain (never flattened to
 * a string). {@see self::context()} carries structured, REDACTED detail
 * (names/types + the decision taken) that Laravel's exception handler merges
 * into the log/monitoring record — no raw secrets or PII.
 */
final class PackageBootException extends RuntimeException
{
    public function __construct(
        public readonly string $builder,
        public readonly BootCriticality $criticality,
        Throwable $previous,
    ) {
        parent::__construct(
            "boot builder [{$builder}] failed: {$previous->getMessage()}",
            (int) $previous->getCode(),
            $previous,
        );
    }

    public static function from(string $builder, BootCriticality $criticality, Throwable $previous): self
    {
        return new self($builder, $criticality, $previous);
    }

    /**
     * Structured context for the central handler (rule 13). Redacted (rule
     * 15): names/types and the decision only — the raw cause message is NOT
     * duplicated here (it already lives on the exception message + chain, and
     * may carry sensitive data). The original throwable stays reachable via
     * getPrevious().
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        $previous = $this->getPrevious();

        return [
            'builder' => $this->builder,
            'criticality' => $this->criticality->name,
            'decision' => $this->criticality === BootCriticality::Critical
                ? 'crashed'
                : 'degraded-and-continued',
            'cause_type' => $previous instanceof Throwable ? $previous::class : null,
        ];
    }
}
