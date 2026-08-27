<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Widgets;

use Override;
use Simtabi\Laranail\Console\Tools\Support\Style;

/**
 * A rounded {@see Badge}: half-block caps (Unicode) coloured as the role tint it
 * around the filled label, suggesting a pill shape. ASCII terminals fall back to
 * parenthesised caps.
 */
final class Pill extends Badge
{
    #[Override]
    public static function make(string $label, string $role = 'primary'): static
    {
        return new self($label, $role);
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function renderLines(): array
    {
        $body = parent::renderLines()[0];
        $bg = $this->background();
        $capStyle = Style::make($this->capabilities())->fg($bg);

        if ($this->capabilities()->supportsUnicode()) {
            return [$capStyle->apply('▐') . $body . $capStyle->apply('▌')];
        }

        return ['(' . $body . ')'];
    }
}
