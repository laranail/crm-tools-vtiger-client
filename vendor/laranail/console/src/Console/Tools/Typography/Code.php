<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Typography;

use Simtabi\Laranail\Console\Tools\Concerns\RendersBlock;
use Simtabi\Laranail\Console\Tools\Contracts\Renderable;
use Simtabi\Laranail\Console\Tools\Formatting\ConsoleUIFormatter;
use Simtabi\Laranail\Console\Tools\Theme\Theme;
use Stringable;

/**
 * Inline code — themed (accent), padded with hair spaces so it reads as a token.
 */
final readonly class Code implements Renderable, Stringable
{
    use RendersBlock;

    private Theme $theme;

    private string $text;

    public function __construct(string $text, ?Theme $theme = null)
    {
        $this->theme = $theme ?? Theme::resolve();
        $this->text = ConsoleUIFormatter::sanitizeText($text);
    }

    public static function make(string $text): self
    {
        return new self($text);
    }

    /**
     * @return list<string>
     */
    public function renderLines(): array
    {
        return [$this->theme->style('code')->apply(' ' . $this->text . ' ')];
    }
}
