<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console;

use Simtabi\Laranail\Console\Exceptions\ConsoleException;
use Simtabi\Laranail\Console\Prompter\Prompter;
use Simtabi\Laranail\Console\Tools\Document\Document;
use Simtabi\Laranail\Console\Tools\Document\Markdown;
use Simtabi\Laranail\Console\Tools\Formatting\ConsoleUIFormatter;
use Simtabi\Laranail\Console\Tools\Services\ConsoleWriter;
use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Support\Color;
use Simtabi\Laranail\Console\Tools\Support\ConfigValidator;
use Simtabi\Laranail\Console\Tools\Support\ConsoleWriterFactory;
use Simtabi\Laranail\Console\Tools\Support\Emoji;
use Simtabi\Laranail\Console\Tools\Support\Keypress;
use Simtabi\Laranail\Console\Tools\Support\Live;
use Simtabi\Laranail\Console\Tools\Support\Os;
use Simtabi\Laranail\Console\Tools\Support\Style;
use Simtabi\Laranail\Console\Tools\Support\Symbols;
use Simtabi\Laranail\Console\Tools\Support\Terminal;
use Simtabi\Laranail\Console\Tools\Theme\Theme;
use Simtabi\Laranail\Console\Tools\Typography\BlockQuote;
use Simtabi\Laranail\Console\Tools\Typography\Code;
use Simtabi\Laranail\Console\Tools\Typography\CodeBlock;
use Simtabi\Laranail\Console\Tools\Typography\Heading;
use Simtabi\Laranail\Console\Tools\Typography\Link;
use Simtabi\Laranail\Console\Tools\Typography\ListBlock;
use Simtabi\Laranail\Console\Tools\Typography\Paragraph;
use Simtabi\Laranail\Console\Tools\Typography\Quote;
use Simtabi\Laranail\Console\Tools\Typography\Text;
use Simtabi\Laranail\Console\Tools\Widgets\AnimatedBar;
use Simtabi\Laranail\Console\Tools\Widgets\Badge;
use Simtabi\Laranail\Console\Tools\Widgets\Banner;
use Simtabi\Laranail\Console\Tools\Widgets\BarChart;
use Simtabi\Laranail\Console\Tools\Widgets\Box;
use Simtabi\Laranail\Console\Tools\Widgets\Button;
use Simtabi\Laranail\Console\Tools\Widgets\ButtonGroup;
use Simtabi\Laranail\Console\Tools\Widgets\ColumnChart;
use Simtabi\Laranail\Console\Tools\Widgets\Columns;
use Simtabi\Laranail\Console\Tools\Widgets\Gauge;
use Simtabi\Laranail\Console\Tools\Widgets\Header;
use Simtabi\Laranail\Console\Tools\Widgets\Heatmap;
use Simtabi\Laranail\Console\Tools\Widgets\Histogram;
use Simtabi\Laranail\Console\Tools\Widgets\KeyValue;
use Simtabi\Laranail\Console\Tools\Widgets\LineChart;
use Simtabi\Laranail\Console\Tools\Widgets\Menu\Menu;
use Simtabi\Laranail\Console\Tools\Widgets\Panel;
use Simtabi\Laranail\Console\Tools\Widgets\Pill;
use Simtabi\Laranail\Console\Tools\Widgets\ProgressBar;
use Simtabi\Laranail\Console\Tools\Widgets\Rule;
use Simtabi\Laranail\Console\Tools\Widgets\ScatterPlot;
use Simtabi\Laranail\Console\Tools\Widgets\Sparkline;
use Simtabi\Laranail\Console\Tools\Widgets\Spinner;
use Simtabi\Laranail\Console\Tools\Widgets\StackedBar;
use Simtabi\Laranail\Console\Tools\Widgets\StatusLine;
use Simtabi\Laranail\Console\Tools\Widgets\StepFlow;
use Simtabi\Laranail\Console\Tools\Widgets\Summary;
use Simtabi\Laranail\Console\Tools\Widgets\Table;
use Simtabi\Laranail\Console\Tools\Widgets\TaskProgress\TaskProgress;
use Simtabi\Laranail\Console\Tools\Widgets\Tree;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Tui\Tui;

/**
 * Unified entry point for the laranail/console package.
 *
 * A thin aggregator: each accessor returns the real domain object (with its
 * full fluent API), so this class never proxies methods. It is the single
 * place the Tools and Prompter sub-domains meet — the sub-domains themselves
 * stay decoupled.
 */
final class ConsoleManager
{
    /**
     * Console output toolkit (formatter, badges, status lines).
     */
    public function ui(): ConsoleUIFormatter
    {
        return ConsoleUIFormatter::create();
    }

    /**
     * A fluent, immutable output writer (styling, context statuses, emoji).
     */
    public function writer(?OutputInterface $output = null): ConsoleWriter
    {
        return app(ConsoleWriterFactory::class)->for($output);
    }

    /**
     * A fluent activity spinner.
     */
    public function spinner(string $message = ''): Spinner
    {
        return Spinner::make($message);
    }

    /**
     * A flavoured progress bar (percent / elapsed / ETA / rate).
     */
    public function progress(?OutputInterface $output = null, int $max = 0): ProgressBar
    {
        return ProgressBar::make($output, $max);
    }

    /**
     * One-line status messages with coloured glyph prefixes.
     */
    public function status(): StatusLine
    {
        return StatusLine::make();
    }

    /**
     * A full-width horizontal divider with an optional inline title.
     */
    public function rule(string $title = ''): Rule
    {
        return Rule::make($title);
    }

    /**
     * Frame text in a box.
     *
     * @param list<string>|string $content
     */
    public function box(array|string $content = []): Box
    {
        return Box::make($content);
    }

    /**
     * Build a nested tree.
     */
    public function tree(string $label = ''): Tree
    {
        return Tree::make($label);
    }

    /**
     * Build a data table.
     */
    public function table(): Table
    {
        return Table::make();
    }

    /**
     * Flow a flat list of items into balanced columns.
     *
     * @param list<string> $items
     */
    public function columns(array $items): Columns
    {
        return Columns::make($items);
    }

    /**
     * An aligned key/value definition list.
     *
     * @param array<string, scalar|null> $pairs
     */
    public function keyValue(array $pairs = []): KeyValue
    {
        return KeyValue::make($pairs);
    }

    /**
     * A single-value gauge/meter.
     */
    public function gauge(float $value, float $max = 100.0): Gauge
    {
        return Gauge::make($value, $max);
    }

    /**
     * An inline block-eighths sparkline.
     *
     * @param list<int|float> $values
     */
    public function sparkline(array $values): Sparkline
    {
        return Sparkline::make($values);
    }

    /**
     * A labelled horizontal bar chart (responsive, themed).
     *
     * @param array<string, int|float> $data label => value
     */
    public function barChart(array $data = []): BarChart
    {
        return BarChart::make($data);
    }

    /**
     * A labelled vertical bar (column) chart.
     *
     * @param array<string, int|float> $data label => value
     */
    public function columnChart(array $data = []): ColumnChart
    {
        return ColumnChart::make($data);
    }

    /**
     * A braille line chart of one or more numeric series.
     *
     * @param array<string, list<int|float>>|list<int|float> $series
     */
    public function lineChart(array $series = []): LineChart
    {
        return LineChart::make($series);
    }

    /**
     * A braille scatter plot of (x, y) points.
     *
     * @param list<array{0: int|float, 1: int|float}> $points
     */
    public function scatterPlot(array $points = []): ScatterPlot
    {
        return ScatterPlot::make($points);
    }

    /**
     * A colour-intensity heatmap of a 2D matrix.
     *
     * @param list<list<int|float>> $matrix
     */
    public function heatmap(array $matrix = []): Heatmap
    {
        return Heatmap::make($matrix);
    }

    /**
     * A frequency histogram of raw values (binned).
     *
     * @param list<int|float> $values
     */
    public function histogram(array $values = []): Histogram
    {
        return Histogram::make($values);
    }

    /**
     * A single proportion (stacked) bar with a legend.
     *
     * @param array<string, int|float> $data label => value
     */
    public function stackedBar(array $data = []): StackedBar
    {
        return StackedBar::make($data);
    }

    /**
     * A centred start-of-run banner/masthead.
     */
    public function banner(string $title): Banner
    {
        return Banner::make($title);
    }

    /**
     * A glyph-prefixed section header with an optional item count.
     */
    public function header(string $title): Header
    {
        return Header::make($title);
    }

    /**
     * An execution-summary block (statistics, performance, errors, status badges).
     *
     * @param array<string, mixed> $stats
     */
    public function summary(array $stats, ?string $title = null): Summary
    {
        return Summary::make($stats, $title);
    }

    /**
     * A wizard/pipeline breadcrumb (done / current / pending).
     *
     * @param list<string> $steps
     */
    public function steps(array $steps = []): StepFlow
    {
        return StepFlow::make($steps);
    }

    /**
     * A multi-task progress widget (exit non-zero on any failure).
     */
    public function tasks(?OutputInterface $output = null): TaskProgress
    {
        return TaskProgress::make($output);
    }

    /**
     * Truecolor / hex / gradient colouring.
     */
    public function color(): Color
    {
        return Color::make();
    }

    /**
     * Emoji helper — Unicode or ASCII, configurable per call (auto/unicode/ascii).
     */
    public function emoji(): Emoji
    {
        return Emoji::make();
    }

    /**
     * A chainable text style (fg/bg + bold/italic/underline…), capability-aware.
     */
    public function style(): Style
    {
        return Style::make();
    }

    /**
     * Resolve a named glyph (ascii/unicode per terminal), e.g. `symbol('arrow')`.
     */
    public function symbol(string $name): string
    {
        return Symbols::for(Capabilities::detect())->get($name);
    }

    /**
     * Operating-system + runtime-environment detection.
     */
    public function os(): Os
    {
        return Os::make();
    }

    /**
     * The active design-system theme (semantic palette + element styles).
     */
    public function theme(): Theme
    {
        return Theme::resolve();
    }

    /**
     * Validate the `console.*` config; returns a list of human-readable errors
     * (empty = valid). See also the `laranail::console.check` command.
     *
     * @return list<string>
     */
    public function validateConfig(): array
    {
        return ConfigValidator::validate();
    }

    /**
     * A fluent inline text builder (colour/style + emoji + symbols + theme roles).
     */
    public function text(string $text = ''): Text
    {
        return Text::make($text);
    }

    /**
     * A word-wrapped, themeable, responsive prose block.
     */
    public function paragraph(string $text): Paragraph
    {
        return Paragraph::make($text);
    }

    /**
     * A themed heading (levels 1–6).
     */
    public function heading(string $text, int $level = 1): Heading
    {
        return Heading::make($text, $level);
    }

    /**
     * A themed list (unordered / ordered / task / definition).
     *
     * @param list<string> $items
     */
    public function list(array $items = []): ListBlock
    {
        return ListBlock::make($items);
    }

    /**
     * A themed OSC-8 hyperlink with a plain-text fallback.
     */
    public function link(string $label, string $url): Link
    {
        return Link::make($label, $url);
    }

    /**
     * A short inline quotation.
     */
    public function quote(string $text): Quote
    {
        return Quote::make($text);
    }

    /**
     * A block quote (themed left bar + wrapped body).
     */
    public function blockQuote(string $text): BlockQuote
    {
        return BlockQuote::make($text);
    }

    /**
     * Inline code.
     */
    public function code(string $text): Code
    {
        return Code::make($text);
    }

    /**
     * A fenced code block (optional caption).
     */
    public function codeBlock(string $code): CodeBlock
    {
        return CodeBlock::make($code);
    }

    /**
     * A fluent document composer (typography + widgets into one themed page).
     */
    public function document(): Document
    {
        return Document::make();
    }

    /**
     * Render a Markdown subset to the terminal via the design-system theme.
     */
    public function markdown(string $markdown): Markdown
    {
        return Markdown::make($markdown);
    }

    /**
     * A live-render engine (animate any renderable in place; TTY-guarded).
     */
    public function live(?OutputInterface $output = null): Live
    {
        return Live::make($output);
    }

    /**
     * A live loading/progress bar (determinate fraction or indeterminate tick).
     */
    public function animatedBar(): AnimatedBar
    {
        return AnimatedBar::make();
    }

    /**
     * A themed inline badge coloured by a semantic role.
     */
    public function badge(string $label, string $role = 'primary'): Badge
    {
        return Badge::make($label, $role);
    }

    /**
     * A themed rounded badge (pill).
     */
    public function pill(string $label, string $role = 'primary'): Pill
    {
        return Pill::make($label, $role);
    }

    /**
     * A visual button affordance (use buttonGroup() for an interactive choice).
     */
    public function button(string $label, string $role = 'primary'): Button
    {
        return Button::make($label, $role);
    }

    /**
     * An interactive single-choice control rendered as buttons.
     *
     * @param array<int|string, string>|list<string> $options
     */
    public function buttonGroup(array $options = []): ButtonGroup
    {
        return ButtonGroup::make($options);
    }

    /**
     * A multi-block layout (vertical/horizontal, nestable).
     */
    public function panel(): Panel
    {
        return Panel::make();
    }

    /**
     * A native interactive menu (key-driven on a TTY, prompts fallback otherwise).
     *
     * @param array<int|string, string>|list<string> $options
     */
    public function menu(string $title = '', array $options = []): Menu
    {
        return Menu::make($title, $options);
    }

    /**
     * Low-level terminal control (bell, tab title, alt-screen, cursor/erase).
     */
    public function terminal(?OutputInterface $output = null): Terminal
    {
        return Terminal::make($output);
    }

    /**
     * Raw key/arrow reader (POSIX TTY; degrades gracefully elsewhere).
     */
    public function keypress(): Keypress
    {
        return Keypress::make();
    }

    /**
     * A symfony/tui full-screen app. Mount our widgets with
     * Console\Tui\RenderableWidget::of(...). Requires the optional symfony/tui
     * package (PHP >= 8.4.1) — install it with `composer require symfony/tui`.
     *
     * @throws ConsoleException when symfony/tui is not installed
     */
    public function tui(): Tui
    {
        if (! class_exists(Tui::class)) {
            throw ConsoleException::fromKey('console.tui_unavailable');
        }

        return new Tui;
    }

    /**
     * Detected terminal capabilities (TTY, colour, Unicode, width).
     */
    public function capabilities(): Capabilities
    {
        return Capabilities::detect();
    }

    /**
     * Interactive input toolkit (prompts, forms, validators).
     */
    public function prompter(): Prompter
    {
        return Prompter::create();
    }
}
