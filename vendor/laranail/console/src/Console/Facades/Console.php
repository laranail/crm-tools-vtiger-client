<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Facades;

use Illuminate\Support\Facades\Facade;
use Simtabi\Laranail\Console\ConsoleManager;
use Simtabi\Laranail\Console\Prompter\Prompter;
use Simtabi\Laranail\Console\Tools\Document\Document;
use Simtabi\Laranail\Console\Tools\Document\Markdown;
use Simtabi\Laranail\Console\Tools\Formatting\ConsoleUIFormatter;
use Simtabi\Laranail\Console\Tools\Services\ConsoleWriter;
use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Support\Color;
use Simtabi\Laranail\Console\Tools\Support\Emoji;
use Simtabi\Laranail\Console\Tools\Support\Keypress;
use Simtabi\Laranail\Console\Tools\Support\Live;
use Simtabi\Laranail\Console\Tools\Support\Os;
use Simtabi\Laranail\Console\Tools\Support\Style;
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
 * @method static ConsoleUIFormatter ui()
 * @method static ConsoleWriter writer(?OutputInterface $output = null)
 * @method static Spinner spinner(string $message = '')
 * @method static ProgressBar progress(?OutputInterface $output = null, int $max = 0)
 * @method static StatusLine status()
 * @method static Rule rule(string $title = '')
 * @method static Box box(array|string $content = [])
 * @method static Tree tree(string $label = '')
 * @method static Table table()
 * @method static Columns columns(array $items)
 * @method static KeyValue keyValue(array $pairs = [])
 * @method static Gauge gauge(float $value, float $max = 100.0)
 * @method static Sparkline sparkline(array $values)
 * @method static BarChart barChart(array $data = [])
 * @method static ColumnChart columnChart(array $data = [])
 * @method static LineChart lineChart(array $series = [])
 * @method static ScatterPlot scatterPlot(array $points = [])
 * @method static Heatmap heatmap(array $matrix = [])
 * @method static Histogram histogram(array $values = [])
 * @method static StackedBar stackedBar(array $data = [])
 * @method static Banner banner(string $title)
 * @method static Header header(string $title)
 * @method static Summary summary(array $stats, ?string $title = null)
 * @method static StepFlow steps(array $steps = [])
 * @method static TaskProgress tasks(?OutputInterface $output = null)
 * @method static Color color()
 * @method static Emoji emoji()
 * @method static Style style()
 * @method static string symbol(string $name)
 * @method static Os os()
 * @method static Theme theme()
 * @method static array validateConfig()
 * @method static Text text(string $text = '')
 * @method static Paragraph paragraph(string $text)
 * @method static Heading heading(string $text, int $level = 1)
 * @method static ListBlock list(array $items = [])
 * @method static Link link(string $label, string $url)
 * @method static Quote quote(string $text)
 * @method static BlockQuote blockQuote(string $text)
 * @method static Code code(string $text)
 * @method static CodeBlock codeBlock(string $code)
 * @method static Document document()
 * @method static Markdown markdown(string $markdown)
 * @method static Live live(?OutputInterface $output = null)
 * @method static AnimatedBar animatedBar()
 * @method static Badge badge(string $label, string $role = 'primary')
 * @method static Pill pill(string $label, string $role = 'primary')
 * @method static Button button(string $label, string $role = 'primary')
 * @method static ButtonGroup buttonGroup(array $options = [])
 * @method static Panel panel()
 * @method static Menu menu(string $title = '', array $options = [])
 * @method static Terminal terminal(?OutputInterface $output = null)
 * @method static Keypress keypress()
 * @method static Tui tui()
 * @method static Capabilities capabilities()
 * @method static Prompter prompter()
 *
 * @see ConsoleManager
 *
 * @api This facade is the package's stable public entry point (SemVer-covered).
 */
final class Console extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ConsoleManager::class;
    }
}
