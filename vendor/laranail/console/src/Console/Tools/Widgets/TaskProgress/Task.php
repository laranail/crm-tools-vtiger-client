<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Widgets\TaskProgress;

use Simtabi\Laranail\Console\Tools\Formatting\ConsoleUIFormatter;

/**
 * A single tracked unit of work within a {@see TaskProgress}.
 */
final class Task
{
    public TaskStatus $status = TaskStatus::Pending;

    public int $current = 0;

    public string $note = '';

    public ?float $startedAt = null;

    public ?float $finishedAt = null;

    public function __construct(public readonly string $name, public readonly int $total = 0) {}

    public function start(): self
    {
        $this->status = TaskStatus::Running;
        $this->startedAt ??= microtime(true);

        return $this;
    }

    public function advance(int $step = 1): self
    {
        $this->startedAt ??= microtime(true);
        $this->status = TaskStatus::Running;
        $this->current = $this->total > 0 ? min($this->current + $step, $this->total) : $this->current + $step;

        return $this;
    }

    public function succeed(string $note = ''): self
    {
        return $this->finish(TaskStatus::Success, $note);
    }

    public function fail(string $note = ''): self
    {
        return $this->finish(TaskStatus::Failed, $note);
    }

    public function skip(string $note = ''): self
    {
        return $this->finish(TaskStatus::Skipped, $note);
    }

    public function warn(string $note = ''): self
    {
        $this->status = TaskStatus::Warning;
        $this->note = ConsoleUIFormatter::sanitizeText($note);

        return $this;
    }

    public function finish(TaskStatus $status, string $note = ''): self
    {
        $this->status = $status;
        $this->note = ConsoleUIFormatter::sanitizeText($note);
        $this->finishedAt ??= microtime(true);

        if ($this->total > 0) {
            $this->current = $this->total;
        }

        return $this;
    }

    public function elapsed(): float
    {
        if ($this->startedAt === null) {
            return 0.0;
        }

        return ($this->finishedAt ?? microtime(true)) - $this->startedAt;
    }

    public function percent(): ?int
    {
        if ($this->total <= 0) {
            return null;
        }

        return (int) round(min(1.0, $this->current / $this->total) * 100);
    }

    /**
     * Estimated time remaining (seconds), or null when it can't be computed
     * (no total, no progress yet, or not started). Returns 0.0 once finished.
     */
    public function eta(): ?float
    {
        if ($this->total <= 0 || $this->current <= 0 || $this->startedAt === null) {
            return null;
        }

        if ($this->finishedAt !== null) {
            return 0.0;
        }

        $elapsed = $this->elapsed();
        $estimatedTotal = $elapsed / $this->current * $this->total;

        return max($estimatedTotal - $elapsed, 0.0);
    }
}
