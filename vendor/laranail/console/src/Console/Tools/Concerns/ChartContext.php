<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Concerns;

use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Theme\Theme;

/**
 * Shared context for the chart widgets: the resolved {@see Capabilities} and
 * {@see Theme}, plus the common `width()` / `responsive()` sizing controls. Each
 * chart calls {@see initContext()} from its constructor (after ingesting its data).
 *
 * @internal Implementation detail of the chart widgets; not a public extension point.
 */
trait ChartContext
{
    /** @var list<string> Theme roles cycled to colour successive series/segments. */
    private const array CYCLE_ROLES = ['primary', 'accent', 'success', 'warning', 'info', 'danger'];

    private ?int $width = null;

    private bool $responsive = true;

    // Set once via initContext() from the using chart's constructor (a trait can't
    // own the constructor, so these can't be `readonly`); treat as write-once.
    private Capabilities $capabilities;

    private Theme $theme;

    private function initContext(?Capabilities $capabilities, ?Theme $theme): void
    {
        $this->capabilities = $capabilities ?? Capabilities::detect();
        $this->theme = $theme ?? Theme::resolve();
    }

    public function width(int $width): self
    {
        $this->width = max($width, 1);

        return $this;
    }

    public function responsive(bool $responsive = true): self
    {
        $this->responsive = $responsive;

        return $this;
    }

    /** The theme role for series/segment number $index (cycled). */
    private function cycleRole(int $index): string
    {
        return self::CYCLE_ROLES[$index % count(self::CYCLE_ROLES)];
    }
}
