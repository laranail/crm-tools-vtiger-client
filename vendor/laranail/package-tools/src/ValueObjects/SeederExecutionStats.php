<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Immutable statistics describing a seeder execution run.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class SeederExecutionStats implements Arrayable, JsonSerializable
{
    /**
     * @param int $total Total number of seeders attempted
     * @param int $success Number that ran without throwing
     * @param int $failed Number that threw
     * @param float $totalTime Total execution time in milliseconds
     * @param list<array{class: string, message: string, package?: string}> $errors
     * @param string|null $group Group/namespace identifier, if applicable
     */
    public function __construct(
        public int $total,
        public int $success,
        public int $failed,
        public float $totalTime = 0.0,
        public array $errors = [],
        public ?string $group = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            total: (int) ($data['total'] ?? 0),
            success: (int) ($data['success'] ?? 0),
            failed: (int) ($data['failed'] ?? 0),
            totalTime: (float) ($data['totalTime'] ?? 0.0),
            errors: $data['errors'] ?? [],
            group: $data['group'] ?? null,
        );
    }

    public static function empty(?string $group = null): self
    {
        return new self(0, 0, 0, 0.0, [], $group);
    }

    public function isSuccessful(): bool
    {
        return $this->failed === 0 && $this->total > 0;
    }

    public function hasFailures(): bool
    {
        return $this->failed > 0;
    }

    public function isEmpty(): bool
    {
        return $this->total === 0;
    }

    public function getSuccessRate(): float
    {
        if ($this->total === 0) {
            return 100.0;
        }

        return round(($this->success / $this->total) * 100, 2);
    }

    public function getAverageTime(): float
    {
        if ($this->total === 0) {
            return 0.0;
        }

        return round($this->totalTime / $this->total, 2);
    }

    public function getFormattedTotalTime(): string
    {
        if ($this->totalTime >= 1000) {
            return number_format($this->totalTime / 1000, 2) . 's';
        }

        return number_format($this->totalTime, 2) . 'ms';
    }

    /**
     * @return list<string>
     */
    public function getErrorMessages(): array
    {
        return array_map(
            static fn (array $error): string => $error['message'] ?? 'Unknown error',
            $this->errors,
        );
    }

    public function getSummary(): string
    {
        if ($this->isEmpty()) {
            return 'No seeders executed';
        }

        $status = $this->isSuccessful()
            ? 'completed successfully'
            : "completed with {$this->failed} failures";

        return "{$this->success}/{$this->total} seeders {$status} in {$this->getFormattedTotalTime()}";
    }

    public function merge(self $other): self
    {
        return new self(
            total: $this->total + $other->total,
            success: $this->success + $other->success,
            failed: $this->failed + $other->failed,
            totalTime: $this->totalTime + $other->totalTime,
            errors: array_merge($this->errors, $other->errors),
            group: $this->group ?? $other->group,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'success' => $this->success,
            'failed' => $this->failed,
            'totalTime' => $this->totalTime,
            'errors' => $this->errors,
            'group' => $this->group,
            'successRate' => $this->getSuccessRate(),
            'isSuccessful' => $this->isSuccessful(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
