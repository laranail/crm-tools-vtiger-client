<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Document;

use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Theme\Theme;
use Simtabi\Laranail\Console\Tools\Typography\BlockQuote;
use Simtabi\Laranail\Console\Tools\Typography\CodeBlock;
use Simtabi\Laranail\Console\Tools\Typography\ListBlock;
use Simtabi\Laranail\Console\Tools\Typography\Paragraph;
use Simtabi\Laranail\Console\Tools\Widgets\Rule;
use Simtabi\Laranail\Console\Tools\Widgets\Table;
use Stringable;

/**
 * Render a documented Markdown subset to the terminal via the {@see Document}
 * composer + the design-system theme. Supported (block-level): ATX headings
 * (#–######), paragraphs, unordered/ordered/task lists, blockquotes, fenced code
 * blocks (with php/json/bash/yaml/js highlighting), horizontal rules. Inline
 * emphasis (`**bold**`, `*italic*`, `` `code` ``) and links `[label](url)` render
 * with real ANSI styling in paragraphs, list items and blockquotes (via
 * {@see InlineMarkup}); headings use the plain normalisation. `:emoji:` shortcodes
 * are resolved throughout.
 */
final readonly class Markdown implements Stringable
{
    private Capabilities $capabilities;

    private Theme $theme;

    public function __construct(private string $markdown, ?Capabilities $capabilities = null, ?Theme $theme = null)
    {
        $this->capabilities = $capabilities ?? Capabilities::detect();
        $this->theme = $theme ?? Theme::resolve();
    }

    public static function make(string $markdown): self
    {
        return new self($markdown);
    }

    public function toDocument(): Document
    {
        $doc = new Document($this->capabilities, $this->theme);
        $lines = preg_split('/\r\n|\r|\n/', $this->markdown) ?: [];
        $count = count($lines);

        for ($i = 0; $i < $count; $i++) {
            $line = $lines[$i];
            $trimmed = trim($line);

            if ($trimmed === '') {
                continue;
            }

            // Fenced code block.
            if (preg_match('/^```+\s*([\w.-]*)\s*$/', $trimmed, $m) === 1) {
                $code = [];
                $caption = $m[1];
                $i++;
                while ($i < $count && preg_match('/^```+\s*$/', trim($lines[$i])) !== 1) {
                    $code[] = $lines[$i];
                    $i++;
                }
                $block = new CodeBlock(implode("\n", $code), $this->capabilities, $this->theme);
                if ($caption !== '') {
                    $block = $block->caption($caption)->language($caption);
                }
                $doc->add($block);

                continue;
            }

            // Horizontal rule.
            if (preg_match('/^(-{3,}|\*{3,}|_{3,})$/', $trimmed) === 1) {
                $doc->add(Rule::make());

                continue;
            }

            // Heading.
            if (preg_match('/^(#{1,6})\s+(.*)$/', $trimmed, $m) === 1) {
                $doc->heading($this->inline($m[2]), strlen($m[1]));

                continue;
            }

            // GFM table: a header row, a `---|---` separator, then data rows.
            if ($this->isTableAt($lines, $i)) {
                $headers = $this->splitTableRow($trimmed);
                $columns = count($headers);
                $i += 2;
                $rows = [];
                while ($i < $count && trim($lines[$i]) !== '' && str_contains($lines[$i], '|')) {
                    $cells = array_slice(array_pad($this->splitTableRow(trim($lines[$i])), $columns, ''), 0, $columns);
                    $rows[] = $cells;
                    $i++;
                }
                $i--;
                $doc->add(new Table($this->capabilities)->headers($headers)->rows($rows));

                continue;
            }

            // Blockquote (consecutive `>` lines).
            if (str_starts_with($trimmed, '>')) {
                $quote = [];
                while ($i < $count && str_starts_with(trim($lines[$i]), '>')) {
                    $quote[] = ltrim(ltrim(trim($lines[$i]), '>'));
                    $i++;
                }
                $i--;
                $doc->add(
                    new BlockQuote($this->inlineStyled(implode(' ', $quote)), $this->capabilities, $this->theme)->rich(),
                );

                continue;
            }

            // Task list (consecutive `- [ ]` / `- [x]`).
            if (preg_match('/^[-*]\s+\[[ xX]\]\s+/', $trimmed) === 1) {
                $tasks = [];
                while ($i < $count && preg_match('/^[-*]\s+\[([ xX])\]\s+(.*)$/', trim($lines[$i]), $m) === 1) {
                    $tasks[$this->inlineStyled($m[2])] = strtolower($m[1]) === 'x';
                    $i++;
                }
                $i--;
                $doc->add(new ListBlock([], $this->capabilities, $this->theme)->tasks($tasks)->rich());

                continue;
            }

            // Ordered list.
            if (preg_match('/^\d+\.\s+/', $trimmed) === 1) {
                $items = [];
                while ($i < $count && preg_match('/^\d+\.\s+(.*)$/', trim($lines[$i]), $m) === 1) {
                    $items[] = $this->inlineStyled($m[1]);
                    $i++;
                }
                $i--;
                $doc->add(new ListBlock($items, $this->capabilities, $this->theme)->ordered()->rich());

                continue;
            }

            // Unordered list.
            if (preg_match('/^[-*]\s+/', $trimmed) === 1) {
                $items = [];
                while ($i < $count && preg_match('/^[-*]\s+(.*)$/', trim($lines[$i]), $m) === 1
                    && preg_match('/^[-*]\s+\[[ xX]\]/', trim($lines[$i])) !== 1) {
                    $items[] = $this->inlineStyled($m[1]);
                    $i++;
                }
                $i--;
                $doc->add(new ListBlock($items, $this->capabilities, $this->theme)->rich());

                continue;
            }

            // Paragraph (consecutive non-blank, non-special lines).
            $para = [];
            while ($i < $count && trim($lines[$i]) !== '' && ! $this->isBlockStart(trim($lines[$i])) && ! $this->isTableAt($lines, $i)) {
                $para[] = trim($lines[$i]);
                $i++;
            }
            $i--;
            // Paragraphs, lists and quotes get full inline styling (bold/italic/
            // code/link); headings use the plain normalisation.
            $doc->add(Paragraph::rich($this->inlineStyled(implode(' ', $para)), $this->capabilities, $this->theme));
        }

        return $doc;
    }

    public function render(): string
    {
        return $this->toDocument()->render();
    }

    public function __toString(): string
    {
        return $this->render();
    }

    private function isBlockStart(string $line): bool
    {
        return preg_match('/^(#{1,6}\s|>|[-*]\s|\d+\.\s|```|-{3,}$|\*{3,}$|_{3,}$)/', $line) === 1;
    }

    /**
     * Does a GFM table begin at line $i? (a pipe row followed by a `---|---`
     * separator). Used both to render tables and to stop a paragraph swallowing one.
     *
     * @param list<string> $lines
     */
    private function isTableAt(array $lines, int $i): bool
    {
        return str_contains($lines[$i] ?? '', '|')
            && isset($lines[$i + 1])
            && $this->isTableSeparator(trim($lines[$i + 1]));
    }

    /**
     * A GFM table separator row, e.g. `| --- | :--: | ---: |`.
     */
    private function isTableSeparator(string $line): bool
    {
        return preg_match('/^\|?\s*:?-+:?\s*(\|\s*:?-+:?\s*)*\|?$/', $line) === 1;
    }

    /**
     * Split a `| a | b |` table row into trimmed, plain-text cells.
     *
     * @return list<string>
     */
    private function splitTableRow(string $line): array
    {
        $line = (string) preg_replace('/^\||\|$/', '', trim($line));
        // Split on unescaped pipes only, then unescape `\|` to a literal pipe.
        $cells = preg_split('/(?<!\\\\)\|/', $line) ?: [];

        return array_values(array_map(
            fn (string $cell): string => $this->inline(trim(str_replace('\\|', '|', $cell))),
            $cells,
        ));
    }

    /**
     * Render inline markdown to a themed, styled string (bold/italic/code/link +
     * emoji). Used for paragraphs, list items and blockquotes.
     */
    private function inlineStyled(string $text): string
    {
        return InlineMarkup::make($this->capabilities, $this->theme)->render($text);
    }

    /**
     * Normalise inline markdown to plain text (emphasis stripped, links → "label (url)").
     * Used for contexts that are already styled by their own role (headings).
     */
    private function inline(string $text): string
    {
        // Links [label](url) → "label (url)".
        $text = (string) preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '$1 ($2)', $text);
        // Bold/italic/inline-code markers stripped (text kept).
        $text = (string) preg_replace('/\*\*([^*]+)\*\*|__([^_]+)__/', '$1$2', $text);
        $text = (string) preg_replace('/\*([^*]+)\*|_([^_]+)_/', '$1$2', $text);

        return (string) preg_replace('/`([^`]+)`/', '$1', $text);
    }
}
