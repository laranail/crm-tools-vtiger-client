<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Typography;

use Simtabi\Laranail\Console\Tools\Concerns\RendersBlock;
use Simtabi\Laranail\Console\Tools\Contracts\Renderable;
use Simtabi\Laranail\Console\Tools\Formatting\ConsoleUIFormatter;
use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Support\DisplayWidth;
use Simtabi\Laranail\Console\Tools\Support\ResponsiveWidth;
use Simtabi\Laranail\Console\Tools\Theme\Theme;
use Stringable;

/**
 * A themed list: unordered, ordered, task (checkbox) or definition. Each item is
 * word-wrapped to the available width with a hanging indent under its marker.
 */
final class ListBlock implements Renderable, Stringable
{
    use RendersBlock;

    private string $type = 'unordered';

    /** @var list<string> */
    private array $items = [];

    /** @var array<string, bool> */
    private array $tasks = [];

    /** @var array<string, string> */
    private array $definitions = [];

    private ?int $width = null;

    private bool $responsive = true;

    private bool $rich = false;

    private readonly Capabilities $capabilities;

    private readonly Theme $theme;

    /**
     * @param list<string> $items
     */
    public function __construct(array $items = [], ?Capabilities $capabilities = null, ?Theme $theme = null)
    {
        $this->items = array_values(array_map(strval(...), $items));
        $this->capabilities = $capabilities ?? Capabilities::detect();
        $this->theme = $theme ?? Theme::resolve();
    }

    /**
     * @param list<string> $items
     */
    public static function make(array $items = []): self
    {
        return new self($items);
    }

    public function unordered(): self
    {
        $this->type = 'unordered';

        return $this;
    }

    public function ordered(): self
    {
        $this->type = 'ordered';

        return $this;
    }

    /**
     * @param array<string, bool> $items label => done
     */
    public function tasks(array $items): self
    {
        $this->type = 'task';
        $this->tasks = $items;

        return $this;
    }

    /**
     * @param array<string, string> $items term => description
     */
    public function definition(array $items): self
    {
        $this->type = 'definition';
        $this->definitions = $items;

        return $this;
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

    /**
     * Treat item text as already-styled (ANSI from InlineMarkup): items are wrapped
     * without re-sanitising. Used by the Markdown renderer so inline emphasis
     * renders inside list items.
     */
    public function rich(bool $rich = true): self
    {
        $this->rich = $rich;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function renderLines(): array
    {
        $cap = ResponsiveWidth::cap($this->width, $this->responsive, $this->capabilities) ?? Paragraph::DEFAULT_WIDTH;

        return match ($this->type) {
            'definition' => $this->renderDefinitions($cap),
            default => $this->renderItems($cap),
        };
    }

    /**
     * @return list<string>
     */
    private function renderItems(int $cap): array
    {
        $out = [];
        $rows = $this->type === 'task' ? array_keys($this->tasks) : $this->items;

        foreach (array_values($rows) as $i => $label) {
            [$plainMarker, $marker] = $this->marker($i, (string) $label);
            $indent = DisplayWidth::of($plainMarker);
            $bodyWidth = max($cap - $indent, 1);

            $wrapped = $this->rich
                ? Paragraph::rich((string) $label, $this->capabilities, $this->theme)->width($bodyWidth)->responsive(false)->renderLines()
                : Paragraph::make(ConsoleUIFormatter::sanitizeText((string) $label))->width($bodyWidth)->responsive(false)->renderLines();

            foreach ($wrapped as $j => $line) {
                $out[] = ($j === 0 ? $marker : str_repeat(' ', $indent)) . $line;
            }
        }

        return $out === [] ? [''] : $out;
    }

    /**
     * @return list<string>
     */
    private function renderDefinitions(int $cap): array
    {
        $out = [];

        foreach ($this->definitions as $term => $description) {
            $out[] = $this->theme->style('h4')->apply(ConsoleUIFormatter::sanitizeText((string) $term));

            foreach (Paragraph::make((string) $description)->width(max($cap - 2, 1))->responsive(false)->renderLines() as $line) {
                $out[] = '  ' . $line;
            }
        }

        return $out === [] ? [''] : $out;
    }

    /**
     * @return array{0:string,1:string} [plain marker, styled marker]
     */
    private function marker(int $index, string $label): array
    {
        $unicode = $this->capabilities->supportsUnicode();

        return match ($this->type) {
            'ordered' => $this->styledMarker(($index + 1) . '. '),
            'task' => $this->taskMarker((bool) ($this->tasks[$label] ?? false), $unicode),
            default => $this->styledMarker($unicode ? '• ' : '- '),
        };
    }

    /**
     * @return array{0:string,1:string}
     */
    private function styledMarker(string $plain): array
    {
        return [$plain, $this->theme->style('list_marker')->apply($plain)];
    }

    /**
     * @return array{0:string,1:string}
     */
    private function taskMarker(bool $done, bool $unicode): array
    {
        $plain = $done ? ($unicode ? '☑ ' : '[x] ') : ($unicode ? '☐ ' : '[ ] ');
        $style = $this->theme->style($done ? 'success' : 'muted');

        return [$plain, $style->apply($plain)];
    }
}
