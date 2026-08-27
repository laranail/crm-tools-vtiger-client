<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Widgets;

use Simtabi\Laranail\Console\Tools\Formatting\ConsoleUIFormatter;
use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Support\DisplayWidth;
use Simtabi\Laranail\Console\Tools\Support\ResponsiveWidth;
use Stringable;
use Symfony\Component\Console\Helper\Table as SymfonyTable;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A fluent table over Symfony's Table helper with named style presets (incl. a
 * GitHub-flavoured markdown emitter), plus two layout variants:
 *
 *  - grouped(): segment rows under labelled group headers in one frame.
 *  - tree():    an indented hierarchy column for nested rows.
 *
 * It also forwards Symfony's column alignment, column widths, header/footer
 * titles, and per-cell styling (via {@see cell()}), and can build itself from a
 * list of associative rows ({@see fromAssoc()}). Falls back to the ASCII style
 * without Unicode support.
 */
final class Table implements Stringable
{
    /** @var array<string, string> preset => Symfony built-in style */
    private const array STYLES = [
        'ascii' => 'default',
        'light' => 'box',
        'double' => 'box-double',
        'compact' => 'compact',
        'borderless' => 'borderless',
        'markdown' => 'markdown',
    ];

    /** @var list<string> */
    private array $headers = [];

    /** @var list<list<string|TableCell>> */
    private array $rows = [];

    /** @var array<string, list<list<string>>> */
    private array $groups = [];

    /** @var list<array{0:int,1:list<string>}> depth + cells */
    private array $treeRows = [];

    /** @var array<int|string, string> column (index or header) => left|right|center */
    private array $align = [];

    /** @var array<int, int> column index => fixed width */
    private array $columnWidths = [];

    /** @var array<int, int> column index => max width */
    private array $maxColumnWidths = [];

    private ?string $headerTitle = null;

    private ?string $footerTitle = null;

    private string $mode = 'plain';

    private string $style = 'light';

    private bool $responsive = true;

    private readonly Capabilities $capabilities;

    public function __construct(?Capabilities $capabilities = null)
    {
        $this->capabilities = $capabilities ?? Capabilities::detect();
    }

    public static function make(): self
    {
        return new self;
    }

    /**
     * Build a styled cell for per-cell alignment / colour inside rows().
     */
    public static function cell(string $value, ?string $align = null, ?string $fg = null, ?string $bg = null): TableCell
    {
        $options = array_filter(
            ['align' => $align, 'fg' => $fg, 'bg' => $bg],
            static fn (?string $v): bool => $v !== null,
        );

        return $options === []
            ? new TableCell(ConsoleUIFormatter::sanitizeText($value))
            : new TableCell(ConsoleUIFormatter::sanitizeText($value), ['style' => new TableCellStyle($options)]);
    }

    /**
     * @param list<string> $headers
     */
    public function headers(array $headers): self
    {
        $this->headers = $headers;

        return $this;
    }

    /**
     * @param list<list<string|TableCell>> $rows
     */
    public function rows(array $rows): self
    {
        $this->rows = $rows;
        $this->mode = 'plain';

        return $this;
    }

    /**
     * Build headers + rows from a list of associative rows (headers = first row's keys).
     *
     * @param list<array<string, scalar|null>> $rows
     */
    public function fromAssoc(array $rows): self
    {
        $this->mode = 'plain';

        if ($rows === []) {
            $this->headers = [];
            $this->rows = [];

            return $this;
        }

        $this->headers = array_map(strval(...), array_keys($rows[0]));
        $this->rows = array_values(array_map(
            static fn (array $row): array => array_values(array_map(
                static fn (mixed $v): string => ConsoleUIFormatter::sanitizeText((string) $v),
                $row,
            )),
            $rows,
        ));

        return $this;
    }

    /**
     * Build headers + rows from a Laravel Collection (or any iterable) of
     * associative rows — headers come from the first row's keys.
     *
     * @param iterable<array<string, scalar|null>> $rows
     */
    public function fromCollection(iterable $rows): self
    {
        return $this->fromAssoc(array_values(is_array($rows) ? $rows : iterator_to_array($rows)));
    }

    /**
     * Segment rows under labelled group headers within a single frame.
     *
     * @param array<string, list<list<string>>> $groups label => rows
     */
    public function grouped(array $groups): self
    {
        $this->groups = $groups;
        $this->mode = 'grouped';

        return $this;
    }

    /**
     * Render an indented hierarchy in the first column.
     *
     * @param list<array{0:int,1:list<string>}> $rows [depth, cells] per row
     */
    public function tree(array $rows): self
    {
        $this->treeRows = $rows;
        $this->mode = 'tree';

        return $this;
    }

    /**
     * Per-column alignment, keyed by column index or header name: left|right|center.
     *
     * @param array<int|string, string> $map
     */
    public function align(array $map): self
    {
        $this->align = $map;

        return $this;
    }

    /**
     * Fixed column widths, keyed by column index.
     *
     * @param array<int, int> $widths
     */
    public function columnWidths(array $widths): self
    {
        $this->columnWidths = $widths;

        return $this;
    }

    public function maxColumnWidth(int $column, int $width): self
    {
        $this->maxColumnWidths[$column] = $width;

        return $this;
    }

    public function title(?string $title): self
    {
        $this->headerTitle = $title === null ? null : ConsoleUIFormatter::sanitizeText($title);

        return $this;
    }

    public function footer(?string $title): self
    {
        $this->footerTitle = $title === null ? null : ConsoleUIFormatter::sanitizeText($title);

        return $this;
    }

    public function style(string $style): self
    {
        $this->style = isset(self::STYLES[$style]) ? $style : 'light';

        return $this;
    }

    /**
     * Clamp the table to the terminal width (default on): cells wrap/shrink so the
     * table never overflows — columns are never dropped. Explicit
     * maxColumnWidth()s and an absence of responsiveness opt out.
     */
    public function responsive(bool $responsive = true): self
    {
        $this->responsive = $responsive;

        return $this;
    }

    public function render(?OutputInterface $output = null): string
    {
        $buffer = new BufferedOutput;
        $style = $this->capabilities->supportsUnicode() ? $this->style : 'ascii';

        $table = new SymfonyTable($buffer);
        $table->setStyle(self::STYLES[$style]);

        if ($this->headers !== []) {
            $table->setHeaders(array_map(ConsoleUIFormatter::sanitizeText(...), $this->headers));
        }

        // Apply column tweaks AFTER setStyle (the preset replaces the active style).
        $this->applyColumns($table);
        $this->applyResponsiveColumns($table);

        // Strip terminal control characters from every cell at render time
        // (TableCell instances are already sanitised in cell()).
        $table->setRows(array_map($this->sanitizeRow(...), $this->buildRows()));
        $table->render();

        $rendered = $buffer->fetch();

        $output?->write($rendered);

        return $rendered;
    }

    /**
     * Cap column widths so a too-wide plain table wraps to fit the terminal.
     * No-op when the table already fits (so normal output is unchanged) or when
     * explicit maxColumnWidth()s are set / responsiveness is off.
     */
    private function applyResponsiveColumns(SymfonyTable $table): void
    {
        if (! $this->responsive || $this->mode !== 'plain' || $this->maxColumnWidths !== [] || ! ResponsiveWidth::enabled()) {
            return;
        }

        $widths = [];
        foreach ($this->headers as $i => $header) {
            $widths[$i] = DisplayWidth::of($header);
        }
        foreach ($this->rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach ($row as $i => $cell) {
                if (is_string($cell)) {
                    $widths[$i] = max($widths[$i] ?? 0, DisplayWidth::of($cell));
                }
            }
        }

        $cols = count($widths);
        if ($cols === 0) {
            return;
        }

        $overhead = ($cols + 1) + ($cols * 2); // separators + per-cell padding
        $terminal = $this->capabilities->width();

        // Only intervene when the natural table would overflow.
        if (array_sum($widths) + $overhead <= $terminal) {
            return;
        }

        $perCol = max((int) floor(($terminal - $overhead) / $cols), 4);
        foreach (array_keys($widths) as $i) {
            $table->setColumnMaxWidth($i, $perCol);
        }
    }

    private function applyColumns(SymfonyTable $table): void
    {
        foreach ($this->columnWidths as $index => $width) {
            $table->setColumnWidth($index, $width);
        }

        foreach ($this->maxColumnWidths as $index => $width) {
            $table->setColumnMaxWidth($index, $width);
        }

        if ($this->headerTitle !== null) {
            $table->setHeaderTitle($this->headerTitle);
        }

        if ($this->footerTitle !== null) {
            $table->setFooterTitle($this->footerTitle);
        }

        foreach ($this->align as $column => $direction) {
            $index = is_int($column) ? $column : array_search($column, $this->headers, true);

            if ($index === false) {
                continue;
            }

            $style = clone $table->getStyle();
            $style->setPadType(match ($direction) {
                'right' => STR_PAD_LEFT,
                'center' => STR_PAD_BOTH,
                default => STR_PAD_RIGHT,
            });

            $table->setColumnStyle((int) $index, $style);
        }
    }

    /**
     * Strip terminal control characters from a row's string cells (TableCell
     * instances and separators pass through untouched).
     *
     * @param list<string|TableCell>|TableCell[]|TableSeparator $row
     * @return list<string|TableCell>|TableCell[]|TableSeparator
     */
    private function sanitizeRow(array|TableSeparator $row): array|TableSeparator
    {
        if ($row instanceof TableSeparator) {
            return $row;
        }

        return array_map(
            static fn (string|TableCell $cell): string|TableCell => $cell instanceof TableCell
                ? $cell
                : ConsoleUIFormatter::sanitizeText($cell),
            $row,
        );
    }

    /**
     * @return list<list<string|TableCell>|TableCell[]|TableSeparator>
     */
    private function buildRows(): array
    {
        return match ($this->mode) {
            'grouped' => $this->buildGroupedRows(),
            'tree' => $this->buildTreeRows(),
            default => $this->rows,
        };
    }

    /**
     * @return list<list<string|TableCell>|TableCell[]|TableSeparator>
     */
    private function buildGroupedRows(): array
    {
        // Span the full table: the widest of the header row and every group row.
        $columns = count($this->headers);
        foreach ($this->groups as $rows) {
            foreach ($rows as $row) {
                $columns = max($columns, count($row));
            }
        }
        $columns = max($columns, 1);

        $built = [];
        $first = true;

        foreach ($this->groups as $label => $rows) {
            if (! $first) {
                $built[] = new TableSeparator;
            }

            $built[] = [new TableCell(ConsoleUIFormatter::sanitizeText((string) $label), ['colspan' => $columns])];

            foreach ($rows as $row) {
                $built[] = $row;
            }

            $first = false;
        }

        return $built;
    }

    /**
     * @return list<list<string>>
     */
    private function buildTreeRows(): array
    {
        $unicode = $this->capabilities->supportsUnicode();
        $stem = $unicode ? '└─ ' : '`- ';
        $built = [];

        foreach ($this->treeRows as [$depth, $cells]) {
            $indent = $depth > 0 ? str_repeat('   ', $depth - 1) . $stem : '';
            $cells[0] = $indent . ($cells[0] ?? '');
            $built[] = $cells;
        }

        return $built;
    }

    public function __toString(): string
    {
        return $this->render();
    }
}
