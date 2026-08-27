<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Concerns;

use Simtabi\Laranail\Console\Tools\Support\DisplayWidth;

/**
 * Shared rendering plumbing for block components (those that implement
 * {@see Renderable}).
 *
 * Property-free and readonly-compatible: a component only implements
 * `renderLines(): list<string>` and gets `render()`, `totalWidth()`,
 * `totalHeight()` and `__toString()` for free — DRY, one place. Each line is
 * expected to be the same display width (the typography components guarantee it).
 *
 * @internal Implementation detail of the block widgets; depend on the
 * {@see Renderable} contract, not on this trait.
 */
trait RendersBlock
{
    /**
     * @return list<string>
     */
    abstract public function renderLines(): array;

    public function render(): string
    {
        return implode("\n", $this->renderLines());
    }

    public function totalWidth(): int
    {
        return DisplayWidth::maxWidth($this->renderLines());
    }

    public function totalHeight(): int
    {
        return count($this->renderLines());
    }

    public function __toString(): string
    {
        return $this->render();
    }
}
