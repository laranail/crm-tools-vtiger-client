<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Widgets\TaskProgress;

use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Support\DisplayWidth;
use Simtabi\Laranail\Console\Tools\Support\Lang;
use Simtabi\Laranail\Console\Tools\Support\TimeFormat;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A multi-task progress widget: a tree of task rows (status glyph, name, count,
 * percent, elapsed, ETA) redrawn in place on a TTY, or emitted one line per
 * event on a non-TTY (CI logs). {@see exitCode()} is non-zero if any task failed.
 */
final class TaskProgress
{
    /** @var list<Task> */
    private array $tasks = [];

    private readonly OutputInterface $output;

    private readonly Capabilities $capabilities;

    private readonly bool $interactive;

    private ?ConsoleSectionOutput $section = null;

    public function __construct(?OutputInterface $output = null, ?Capabilities $capabilities = null)
    {
        $this->output = $output ?? new ConsoleOutput;
        $this->capabilities = $capabilities ?? Capabilities::detect();
        $this->interactive = $this->capabilities->isInteractive() && $this->output instanceof ConsoleOutputInterface;

        if ($this->interactive && $this->output instanceof ConsoleOutputInterface) {
            $this->section = $this->output->section();
        }
    }

    public static function make(?OutputInterface $output = null): self
    {
        return new self($output);
    }

    public function task(string $name, int $total = 0): Task
    {
        $task = new Task($name, $total);
        $this->tasks[] = $task;

        return $task;
    }

    /**
     * Redraw all rows (TTY) or print the latest terminal events (non-TTY).
     */
    public function draw(): self
    {
        $rows = array_map($this->row(...), $this->tasks);

        if ($this->section instanceof ConsoleSectionOutput) {
            $this->section->overwrite(implode("\n", $rows));

            return $this;
        }

        // Non-TTY (CI logs): emit one line per state change, so started, warned
        // and terminal transitions are all recorded — and a task that never
        // finishes still leaves its "running" line rather than vanishing.
        foreach ($this->tasks as $task) {
            $id = spl_object_id($task);
            if ($task->status === TaskStatus::Pending) {
                continue;
            }
            if (($this->emitted[$id] ?? null) === $task->status) {
                continue;
            }

            $this->emitted[$id] = $task->status;
            $this->output->writeln($this->row($task));
        }

        return $this;
    }

    /** @var array<int, TaskStatus> last status emitted on a non-TTY, per task */
    private array $emitted = [];

    /**
     * Print a summary footer; returns the non-zero exit code if any task failed.
     */
    public function finish(): int
    {
        $this->draw();

        $failed = count(array_filter($this->tasks, static fn (Task $t): bool => $t->status === TaskStatus::Failed));
        $total = count($this->tasks);
        $glyph = $this->capabilities->supportsUnicode();

        $marker = $failed === 0
            ? TaskStatus::Success->glyph($glyph)
            : TaskStatus::Failed->glyph($glyph);

        $summary = $marker . ' ' . Lang::get(
            'widgets.task_progress.succeeded',
            ':done/:total tasks succeeded',
            ['done' => $total - $failed, 'total' => $total],
        );

        $this->output->writeln($summary);

        return $this->exitCode();
    }

    public function exitCode(): int
    {
        foreach ($this->tasks as $task) {
            if ($task->status === TaskStatus::Failed) {
                return 1;
            }
        }

        return 0;
    }

    private function row(Task $task): string
    {
        $glyph = $task->status->glyph($this->capabilities->supportsUnicode());
        $name = DisplayWidth::pad($task->name, 32);

        $count = $task->total > 0 ? sprintf('%d/%d', $task->current, $task->total) : '';
        $percent = $task->percent();
        $pct = $percent !== null ? "{$percent}%" : '';
        $elapsed = $task->elapsed() > 0 ? TimeFormat::duration($task->elapsed()) : '';

        $eta = $task->eta();
        $etaStr = ($eta !== null && $eta > 0) ? Lang::get('widgets.task_progress.eta', 'ETA ') . TimeFormat::duration($eta) : '';

        $parts = array_filter([
            $glyph,
            $name,
            DisplayWidth::pad($count, 9),
            DisplayWidth::pad($pct, 5),
            DisplayWidth::pad($elapsed, 8),
            DisplayWidth::pad($etaStr, 11),
            $task->note,
        ], static fn (string $p): bool => $p !== '');

        return rtrim(implode(' ', $parts));
    }
}
