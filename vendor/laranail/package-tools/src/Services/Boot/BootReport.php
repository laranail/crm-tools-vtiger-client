<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Boot;

/**
 * Observable degraded-boot state (rule 7): the set of degradable boot
 * builders that failed but were continued past. A queryable surface — a
 * paged report is not a queryable state — consumed by the boot doctor check
 * and by a consumer `/health/boot` route.
 *
 * Stores REDACTED summaries only (rules 11/15): builder name, criticality,
 * and the exception CLASS — never the raw message, which may carry secrets
 * or PII. Full detail (message, trace) reaches only the access-controlled
 * monitoring pipeline via report().
 */
final class BootReport
{
    /** @var array<string, array{criticality: string, cause_type: ?string}> */
    private array $degraded = [];

    public function recordDegraded(string $builder, string $criticality, ?string $causeType): void
    {
        $this->degraded[$builder] = [
            'criticality' => $criticality,
            'cause_type' => $causeType,
        ];
    }

    public function isHealthy(): bool
    {
        return $this->degraded === [];
    }

    /**
     * @return array<string, array{criticality: string, cause_type: ?string}>
     */
    public function degraded(): array
    {
        return $this->degraded;
    }
}
