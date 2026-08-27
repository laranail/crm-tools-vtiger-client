<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Services;

/**
 * Typed result of a cache optimize/clear orchestration
 * ({@see CacheService::optimize()} / {@see CacheService::clearOptimization()}).
 *
 * Replaces the legacy ad-hoc result arrays (whose keys differed between the
 * success and failure paths) with a single, predictable shape: the ordered list
 * of completed step labels plus a map of step → error message for any that
 * failed. `success` is true only when no step recorded an error.
 */
final readonly class CacheOptimizationResult
{
    /**
     * @param list<string>          $steps  Labels of the steps that completed successfully, in order.
     * @param array<string, string> $errors Map of step label → error message for steps that failed.
     */
    public function __construct(
        public bool $success,
        public array $steps = [],
        public array $errors = [],
    ) {}

    public function successful(): bool
    {
        return $this->success;
    }

    public function failed(): bool
    {
        return !$this->success;
    }

    /**
     * @return array{success: bool, steps: list<string>, errors: array<string, string>}
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'steps' => $this->steps,
            'errors' => $this->errors,
        ];
    }
}
