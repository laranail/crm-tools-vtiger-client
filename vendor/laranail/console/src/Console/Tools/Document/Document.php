<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Document;

use Simtabi\Laranail\Console\Tools\Concerns\RendersBlock;
use Simtabi\Laranail\Console\Tools\Contracts\Renderable;
use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Theme\Theme;
use Simtabi\Laranail\Console\Tools\Typography\BlockQuote;
use Simtabi\Laranail\Console\Tools\Typography\Code;
use Simtabi\Laranail\Console\Tools\Typography\CodeBlock;
use Simtabi\Laranail\Console\Tools\Typography\Heading;
use Simtabi\Laranail\Console\Tools\Typography\Link;
use Simtabi\Laranail\Console\Tools\Typography\ListBlock;
use Simtabi\Laranail\Console\Tools\Typography\Paragraph;
use Simtabi\Laranail\Console\Tools\Typography\Quote;
use Stringable;

/**
 * A fluent document composer: stitch typography + any renderable widget into one
 * themed, responsive page with consistent spacing between blocks.
 *
 *   Console::document()
 *       ->h1('Release notes')
 *       ->paragraph('Highlights for this version:')
 *       ->bulletList(['Faster', 'Safer'])
 *       ->blockQuote('Ship it.')
 *       ->add(Console::table()->fromAssoc($rows))
 *       ->render();
 */
final class Document implements Renderable, Stringable
{
    use RendersBlock;

    /** @var list<Renderable|Stringable|string> */
    private array $blocks = [];

    private int $spacing = 1;

    private ?int $width = null;

    private readonly Capabilities $capabilities;

    private readonly Theme $theme;

    public function __construct(?Capabilities $capabilities = null, ?Theme $theme = null)
    {
        $this->capabilities = $capabilities ?? Capabilities::detect();
        $this->theme = $theme ?? Theme::resolve();
    }

    public static function make(): self
    {
        return new self;
    }

    public function spacing(int $lines): self
    {
        $this->spacing = max($lines, 0);

        return $this;
    }

    public function width(int $width): self
    {
        $this->width = max($width, 1);

        return $this;
    }

    public function heading(string $text, int $level = 1): self
    {
        $heading = new Heading($text, $level, $this->capabilities, $this->theme);

        return $this->add($this->width !== null ? $heading->width($this->width) : $heading);
    }

    public function h1(string $text): self
    {
        return $this->heading($text, 1);
    }

    public function h2(string $text): self
    {
        return $this->heading($text, 2);
    }

    public function h3(string $text): self
    {
        return $this->heading($text, 3);
    }

    public function paragraph(string $text): self
    {
        $p = new Paragraph($text, $this->capabilities, $this->theme);

        return $this->add($this->width !== null ? $p->width($this->width) : $p);
    }

    /**
     * @param list<string> $items
     */
    public function bulletList(array $items): self
    {
        return $this->addList(new ListBlock($items, $this->capabilities, $this->theme));
    }

    /**
     * @param list<string> $items
     */
    public function orderedList(array $items): self
    {
        return $this->addList(new ListBlock($items, $this->capabilities, $this->theme)->ordered());
    }

    /**
     * @param array<string, bool> $items
     */
    public function taskList(array $items): self
    {
        return $this->addList(new ListBlock([], $this->capabilities, $this->theme)->tasks($items));
    }

    /**
     * @param array<string, string> $items
     */
    public function definitionList(array $items): self
    {
        return $this->addList(new ListBlock([], $this->capabilities, $this->theme)->definition($items));
    }

    public function link(string $label, string $url): self
    {
        return $this->add(new Link($label, $url, $this->capabilities, $this->theme));
    }

    public function quote(string $text): self
    {
        return $this->add(new Quote($text, $this->capabilities, $this->theme));
    }

    public function blockQuote(string $text): self
    {
        $q = new BlockQuote($text, $this->capabilities, $this->theme);

        return $this->add($this->width !== null ? $q->width($this->width) : $q);
    }

    public function code(string $text): self
    {
        return $this->add(new Code($text, $this->theme));
    }

    public function codeBlock(string $code): self
    {
        $c = new CodeBlock($code, $this->capabilities, $this->theme);

        return $this->add($this->width !== null ? $c->width($this->width) : $c);
    }

    /**
     * Add a blank-line gap (independent of inter-block spacing).
     */
    public function blank(int $lines = 1): self
    {
        $this->blocks[] = str_repeat("\n", max($lines - 1, 0));

        return $this;
    }

    /**
     * Add any renderable widget or raw string (escape hatch: Table, Panel, Banner,
     * BarChart, …).
     */
    public function add(Renderable|Stringable|string $block): self
    {
        $this->blocks[] = $block;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function renderLines(): array
    {
        $out = [];
        $gap = array_fill(0, $this->spacing, '');

        foreach ($this->blocks as $i => $block) {
            if ($i > 0) {
                $out = [...$out, ...$gap];
            }

            $out = [...$out, ...$this->blockLines($block)];
        }

        return $out === [] ? [''] : $out;
    }

    private function addList(ListBlock $list): self
    {
        return $this->add($this->width !== null ? $list->width($this->width) : $list);
    }

    /**
     * @return list<string>
     */
    private function blockLines(Renderable|Stringable|string $block): array
    {
        if ($block instanceof Renderable) {
            return $block->renderLines();
        }

        return explode("\n", (string) $block);
    }
}
